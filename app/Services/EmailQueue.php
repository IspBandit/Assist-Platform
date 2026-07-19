<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Platform\Brand\BrandContext;
use RuntimeException;

/**
 * Queues outbound email in the database. A cron job (Phase 9) processes the
 * queue via SMTP/PHPMailer. We never call PHP mail() directly.
 */
final class EmailQueue
{
    /**
     * Queue an email built from a stored template, replacing {{placeholders}}.
     *
     * @param array<string,string> $placeholders
     */
    public static function queueTemplate(
        string $templateKey,
        string $recipientEmail,
        ?string $recipientName = null,
        array $placeholders = [],
        ?string $scheduledAt = null
    ): bool {
        $template = Database::selectOne(
            'SELECT * FROM email_templates WHERE template_key = ? AND is_enabled = 1',
            [$templateKey]
        );
        if ($template === null) {
            return false;
        }

        $subject = self::replace((string) $template['subject'], $placeholders);
        $html = self::replace((string) $template['html_body'], $placeholders);
        $text = self::replace((string) ($template['text_body'] ?? ''), $placeholders);

        return self::queueRaw($recipientEmail, $recipientName, $subject, $html, $text, $templateKey, $scheduledAt);
    }

    public static function queueRaw(
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $html,
        string $text = '',
        ?string $templateKey = null,
        ?string $scheduledAt = null
    ): bool {
        Database::query(
            'INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [self::brandDatabaseId(), $templateKey, $recipientEmail, $recipientName, $subject, $html, $text, 'pending', $scheduledAt]
        );
        return true;
    }

    private static function replace(string $body, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        return $body;
    }

    private static function brandDatabaseId(): int
    {
        if (BrandContext::hasCurrent()) {
            return BrandContext::current()->databaseId();
        }

        $default = (string) Config::get('brands.default', '');
        $configured = Config::get('brands.registry.' . $default . '.database_id');
        if (!is_numeric($configured) || (int) $configured < 1) {
            throw new RuntimeException('Background email requires a valid default brand database ID');
        }

        return (int) $configured;
    }
}
