<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Provider;

use App\Helpers\Env;
use App\Platform\AiSearch\Budget\AiCostEstimator;
use App\Platform\AiSearch\Budget\AiSettings;
use RuntimeException;
use Throwable;

/**
 * OpenAI Chat Completions adapter with Structured Outputs.
 * API key from env only — never logged, never stored in MariaDB.
 */
final class OpenAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'openai';
    }

    public function completeStructured(AiCompletionRequest $request): AiCompletionResult
    {
        $started = hrtime(true);
        $settings = AiSettings::get();

        if ($settings['model_allowlist'] === [] || !in_array($request->model, $settings['model_allowlist'], true)) {
            return AiCompletionResult::failure($this->name(), $request->model, 'model_not_allowlisted', $this->ms($started));
        }

        $apiKey = trim((string) Env::get('OPENAI_API_KEY', ''));
        if ($apiKey === '') {
            return AiCompletionResult::failure($this->name(), $request->model, 'missing_api_key', $this->ms($started));
        }

        $base = rtrim((string) Env::get('OPENAI_API_BASE', 'https://api.openai.com/v1'), '/');
        if (!str_starts_with($base, 'https://')) {
            return AiCompletionResult::failure($this->name(), $request->model, 'invalid_api_base', $this->ms($started));
        }

        $payload = [
            'model' => $request->model,
            'messages' => $request->messages,
            'temperature' => 0,
            'max_tokens' => max(32, $request->maxOutputTokens),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->schemaName,
                    'strict' => true,
                    'schema' => $request->jsonSchema,
                ],
            ],
        ];

        $retries = max(0, $settings['max_retries']);
        $attempt = 0;
        $lastFailure = 'provider_failure';

        while ($attempt <= $retries) {
            $attempt++;
            try {
                $raw = $this->httpPostJson($base . '/chat/completions', $payload, $apiKey, $request->timeoutSeconds, $request->correlationId);
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    $lastFailure = 'invalid_json_response';
                    continue;
                }

                if (isset($decoded['error']) && is_array($decoded['error'])) {
                    $lastFailure = 'api_error';
                    // Do not retry auth / invalid request.
                    $code = (string) ($decoded['error']['code'] ?? '');
                    $type = (string) ($decoded['error']['type'] ?? '');
                    if ($type === 'invalid_request_error' || $code === 'invalid_api_key') {
                        break;
                    }
                    continue;
                }

                $choice = $decoded['choices'][0] ?? null;
                if (!is_array($choice)) {
                    $lastFailure = 'empty_choices';
                    continue;
                }

                $message = $choice['message'] ?? null;
                if (!is_array($message)) {
                    $lastFailure = 'empty_message';
                    continue;
                }

                if (!empty($message['refusal']) && is_string($message['refusal'])) {
                    return AiCompletionResult::failure(
                        $this->name(),
                        $request->model,
                        'model_refusal',
                        $this->ms($started),
                        true
                    );
                }

                $content = $message['content'] ?? null;
                if (!is_string($content) || $content === '') {
                    $lastFailure = 'empty_content';
                    continue;
                }

                $parsed = json_decode($content, true);
                if (!is_array($parsed)) {
                    $lastFailure = 'invalid_schema_payload';
                    continue;
                }

                $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
                $inTok = max(0, (int) ($usage['prompt_tokens'] ?? 0));
                $outTok = max(0, (int) ($usage['completion_tokens'] ?? 0));
                $cost = AiCostEstimator::fromUsage($request->model, $inTok, $outTok);

                return new AiCompletionResult(
                    ok: true,
                    parsed: $parsed,
                    provider: $this->name(),
                    model: $request->model,
                    inputTokens: $inTok,
                    outputTokens: $outTok,
                    estimatedCostAud: $cost,
                    actualCostAud: null,
                    durationMs: $this->ms($started),
                    providerRequestId: isset($decoded['id']) && is_string($decoded['id']) ? $decoded['id'] : null,
                    failureReason: null,
                );
            } catch (Throwable $e) {
                $lastFailure = $this->safeFailureClass($e);
                if ($lastFailure === 'timeout' && $attempt <= $retries) {
                    continue;
                }
                if ($attempt > $retries) {
                    break;
                }
            }
        }

        return AiCompletionResult::failure($this->name(), $request->model, $lastFailure, $this->ms($started));
    }

    private function httpPostJson(string $url, array $payload, string $apiKey, int $timeoutSeconds, string $correlationId): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl_unavailable');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('curl_init_failed');
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'X-Request-ID: ' . $correlationId,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeoutSeconds)),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new RuntimeException('timeout');
        }
        if ($raw === false) {
            throw new RuntimeException($error !== '' ? 'curl_error' : 'empty_response');
        }

        return (string) $raw;
    }

    private function safeFailureClass(Throwable $e): string
    {
        $msg = $e->getMessage();
        if ($msg === 'timeout') {
            return 'timeout';
        }
        if ($msg === 'curl_unavailable' || $msg === 'curl_init_failed') {
            return $msg;
        }
        // Never surface provider bodies that might echo prompts/secrets.
        return 'provider_failure';
    }

    private function ms(int $started): int
    {
        return (int) max(0, (hrtime(true) - $started) / 1_000_000);
    }
}
