<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use RuntimeException;
use Throwable;

/**
 * Minimal, dependency-free SMTP client used to deliver queued email when the
 * Composer PHPMailer package is not installed on the host (the platform runs on
 * bare cPanel where `composer install` may never have been run). Supports
 * implicit SSL (port 465) and STARTTLS (port 587), AUTH LOGIN, and sends a
 * multipart/alternative (plain + HTML) message.
 */
final class SmtpClient
{
    /** @var resource */
    private $socket;
    private int $timeout;
    private int $lastCode = 0;
    private string $lastReply = '';
    /** @var string[] Human-readable, password-redacted record of the SMTP conversation. */
    public array $transcript = [];

    private function __construct($socket, int $timeout)
    {
        $this->socket = $socket;
        $this->timeout = $timeout;
    }

    /**
     * Deliver one message using the given effective mail config.
     *
     * @param array<string,mixed> $cfg host, port, username, password,
     *                                  encryption ('ssl'|'tls'|''), from_address, from_name
     */
    public static function send(array $cfg, string $toEmail, string $toName, string $subject, string $html, string $text): void
    {
        $host = trim((string) ($cfg['host'] ?? ''));
        $port = (int) ($cfg['port'] ?? 587);
        $encryption = strtolower(trim((string) ($cfg['encryption'] ?? '')));
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $fromAddress = trim((string) ($cfg['from_address'] ?? '')) ?: $username;
        $fromName = (string) ($cfg['from_name'] ?? 'VanAssist') ?: 'VanAssist';

        if ($host === '' || $fromAddress === '') {
            throw new RuntimeException('SMTP is not configured (missing host or from address).');
        }
        self::assertSafeHeaderValue($host, 'SMTP host');
        self::assertSafeHeaderValue($fromName, 'From name');
        self::assertSafeHeaderValue($toName, 'Recipient name');
        self::assertSafeHeaderValue($subject, 'Subject');
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('SMTP from address is invalid.');
        }
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('SMTP recipient address is invalid.');
        }
        if (!in_array($encryption, ['', 'ssl', 'tls'], true)) {
            throw new RuntimeException('SMTP encryption must be ssl, tls, or empty.');
        }

        $timeout = 30;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $transport = $encryption === 'ssl' ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

        $target = $host . ':' . $port . ' (' . ($encryption !== '' ? $encryption : 'plain') . ')';

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            Logger::error('SMTP connect failed to ' . $target, [
                'to'        => $toEmail,
                'transport' => $transport,
                'error'     => $errstr,
                'errno'     => $errno,
            ], 'email');
            throw new RuntimeException("SMTP connect to {$host}:{$port} failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, $timeout);

        $client = new self($socket, $timeout);
        $client->transcript[] = 'connect ' . $transport;
        $failure = null;
        try {
            $client->expectStep('greeting', 220);
            $ehloHost = self::ehloHostname($fromAddress);
            $client->command("EHLO {$ehloHost}", 250, 'EHLO ' . $ehloHost);

            if ($encryption === 'tls') {
                $client->command('STARTTLS', 220, 'STARTTLS');
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                $client->transcript[] = 'TLS handshake -> ok';
                $client->command("EHLO {$ehloHost}", 250, 'EHLO (post-TLS) ' . $ehloHost);
            }

            if ($username !== '') {
                $client->command('AUTH LOGIN', 334, 'AUTH LOGIN');
                $client->command(base64_encode($username), 334, 'auth username (' . $username . ')');
                $client->command(base64_encode($password), 235, 'auth password (redacted)');
            } else {
                $client->transcript[] = 'auth -> skipped (no username configured)';
            }

            $client->command('MAIL FROM:<' . $fromAddress . '>', 250, 'MAIL FROM <' . $fromAddress . '>');
            $client->command('RCPT TO:<' . $toEmail . '>', [250, 251], 'RCPT TO <' . $toEmail . '>');
            $client->command('DATA', 354, 'DATA');

            $message = self::buildMessage($fromAddress, $fromName, $toEmail, $toName, $subject, $html, $text);
            // Dot-stuffing: any line beginning with a period must be doubled.
            $message = preg_replace('/^\./m', '..', $message) ?? $message;
            $client->write($message . "\r\n.");
            $client->expectStep('message body (' . strlen($message) . ' bytes)', 250);
            // From here the message is accepted by the server. QUIT is cleanup
            // only — never let a QUIT hiccup mark a delivered email as failed
            // (which would cause a duplicate send on retry).
            try {
                $client->write('QUIT');
            } catch (Throwable) {
                // ignore
            }
        } catch (Throwable $e) {
            $failure = $e;
            throw $e;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            if ($failure === null) {
                Logger::info('SMTP delivered to ' . $toEmail . ' via ' . $target, [
                    'subject' => $subject,
                    'steps'   => $client->transcript,
                ], 'email');
            } else {
                Logger::error('SMTP delivery FAILED to ' . $toEmail . ' via ' . $target . ': ' . $failure->getMessage(), [
                    'subject' => $subject,
                    'steps'   => $client->transcript,
                ], 'email');
            }
        }
    }

    private static function ehloHostname(string $fromAddress): string
    {
        $host = gethostname();
        if (is_string($host) && $host !== '') {
            return $host;
        }
        $parts = explode('@', $fromAddress);
        return $parts[1] ?? 'localhost';
    }

    private static function buildMessage(string $fromAddress, string $fromName, string $toEmail, string $toName, string $subject, string $html, string $text): string
    {
        if (trim($text) === '') {
            $text = trim(strip_tags($html));
        }
        $boundary = 'vanassist-' . bin2hex(random_bytes(10));

        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::formatAddress($fromName, $fromAddress),
            'To: ' . self::formatAddress($toName, $toEmail),
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . self::normalize($text) . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . self::normalize($html) . "\r\n\r\n"
            . "--{$boundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private static function normalize(string $s): string
    {
        // Normalise all line endings to CRLF for the wire format.
        return preg_replace('/\r\n|\r|\n/', "\r\n", $s) ?? $s;
    }

    private static function formatAddress(string $name, string $email): string
    {
        $name = trim($name);
        if ($name === '') {
            return '<' . $email . '>';
        }
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7e]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private static function assertSafeHeaderValue(string $value, string $label): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) {
            throw new RuntimeException("{$label} contains prohibited control characters.");
        }
    }

    /** @param int|array<int,int> $expectedCodes */
    private function command(string $line, $expectedCodes, ?string $label = null): void
    {
        $this->write($line);
        $this->expectStep($label ?? $line, $expectedCodes);
    }

    /**
     * Read a reply, record a redacted step in the transcript and throw on an
     * unexpected code (also recording the failing reply for diagnostics).
     *
     * @param int|array<int,int> $expectedCodes
     */
    private function expectStep(string $label, $expectedCodes): void
    {
        try {
            $this->expect($expectedCodes);
            $this->transcript[] = $label . ' -> ' . $this->lastCode . ' ' . self::firstLine($this->lastReply);
        } catch (RuntimeException $e) {
            $this->transcript[] = $label . ' -> FAILED: ' . $e->getMessage();
            throw $e;
        }
    }

    private static function firstLine(string $reply): string
    {
        $reply = trim($reply);
        $nl = strpos($reply, "\n");
        $line = $nl === false ? $reply : substr($reply, 0, $nl);
        // Strip the leading 3-digit status code; the code is logged separately.
        return trim(preg_replace('/^\d{3}[ -]/', '', trim($line)) ?? $line);
    }

    private function write(string $data): void
    {
        $bytes = @fwrite($this->socket, $data . "\r\n");
        if ($bytes === false) {
            throw new RuntimeException('SMTP write failed (connection lost).');
        }
    }

    /** @param int|array<int,int> $expectedCodes */
    private function expect($expectedCodes): void
    {
        $expected = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
        $response = '';
        $code = 0;
        // Read potentially multi-line responses ("250-..." then "250 ...").
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $code = (int) substr($line, 0, 3);
                break;
            }
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                $this->lastCode = $code;
                $this->lastReply = trim($response);
                throw new RuntimeException('SMTP timed out waiting for reply (expected ' . implode('/', $expected) . ').');
            }
        }
        $this->lastCode = $code;
        $this->lastReply = trim($response);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Unexpected SMTP reply (expected ' . implode('/', $expected) . '): ' . trim($response));
        }
    }
}
