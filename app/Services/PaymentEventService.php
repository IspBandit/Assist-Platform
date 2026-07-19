<?php

declare(strict_types=1);

namespace App\Services;

use App\Billing\BillingManager;
use App\Core\Database;
use Throwable;

/**
 * Handles inbound billing webhooks safely. Subscriptions are only ever updated
 * from verified, server-side events — never from browser return-page redirects.
 *
 * Guarantees:
 *  - Signatures are verified before anything is trusted.
 *  - Every event is stored (billing_webhook_events).
 *  - Duplicate delivery is ignored (unique gateway + external_event_id).
 *  - Processing status, attempts and errors are recorded for safe retry.
 */
final class PaymentEventService
{
    /**
     * Ingest a raw webhook. Returns a status string for the HTTP response.
     * Never throws to the caller.
     */
    public function ingest(string $rawPayload, string $signatureHeader): string
    {
        if (!BillingManager::enabled()) {
            return 'billing_disabled';
        }

        $gateway = BillingManager::gateway();

        $verified = $gateway->verifyWebhookSignature($rawPayload, $signatureHeader);
        if (!$verified) {
            $this->storeRaw($gateway->name(), '', '', $rawPayload, false, 'failed', 'signature_verification_failed');
            return 'invalid_signature';
        }

        $event = $gateway->parseWebhookEvent($rawPayload);
        $externalId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($externalId === '') {
            $this->storeRaw($gateway->name(), '', $type, $rawPayload, true, 'failed', 'missing_event_id');
            return 'missing_event_id';
        }

        // Idempotency: ignore an event we've already stored.
        $existing = Database::scalar(
            'SELECT status FROM billing_webhook_events WHERE gateway = ? AND external_event_id = ?',
            [$gateway->name(), $externalId]
        );
        if ($existing !== null && $existing !== false) {
            return 'duplicate_ignored';
        }

        $id = $this->storeRaw($gateway->name(), $externalId, $type, $rawPayload, true, 'received', null);

        try {
            $this->process($type, $event['data'] ?? []);
            $this->mark($id, 'processed', null);
            return 'processed';
        } catch (Throwable $e) {
            $this->mark($id, 'failed', substr($e->getMessage(), 0, 500));
            return 'processing_error';
        }
    }

    /** Re-attempt processing of a stored failed event (admin "retry"). */
    public function retry(int $webhookEventId): string
    {
        $row = Database::selectOne('SELECT * FROM billing_webhook_events WHERE id = ?', [$webhookEventId]);
        if ($row === null) {
            return 'not_found';
        }
        $gateway = BillingManager::gateway();
        $event = $gateway->parseWebhookEvent((string) $row['payload_json']);
        try {
            $this->process((string) ($event['type'] ?? ''), $event['data'] ?? []);
            $this->mark($webhookEventId, 'processed', null);
            return 'processed';
        } catch (Throwable $e) {
            $this->mark($webhookEventId, 'failed', substr($e->getMessage(), 0, 500));
            return 'processing_error';
        }
    }

    /**
     * Translate a verified gateway event into VanAssist state changes. Kept
     * deliberately small for now; concrete subscription/invoice updates are
     * added when the gateway API client is wired in the activation phase.
     */
    private function process(string $type, array $data): void
    {
        // Recognised event types (see spec) — currently recorded as billing_events
        // for an auditable trail. No-op for unknown types.
        $known = [
            'customer.created', 'customer.updated', 'checkout.session.completed',
            'customer.subscription.created', 'customer.subscription.updated',
            'customer.subscription.deleted', 'customer.subscription.trial_will_end',
            'invoice.created', 'invoice.paid', 'invoice.payment_failed',
            'payment_intent.succeeded', 'payment_intent.payment_failed', 'charge.refunded',
        ];

        if (!in_array($type, $known, true)) {
            return;
        }

        Database::query(
            'INSERT INTO billing_events (event_type, subject_type, payload_json, processed, processed_at, created_at) '
            . 'VALUES (?, ?, ?, 1, NOW(), NOW())',
            [$type, 'webhook', json_encode($data) ?: null]
        );
    }

    private function storeRaw(string $gateway, string $externalId, string $type, string $payload, bool $verified, string $status, ?string $error): int
    {
        return Database::insert(
            'INSERT INTO billing_webhook_events '
            . '(gateway, external_event_id, event_type, signature_verified, payload_json, status, attempts, last_error, received_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())',
            [$gateway, $externalId !== '' ? $externalId : uniqid('unverified_', true), $type, $verified ? 1 : 0, $payload, $status, $error]
        );
    }

    private function mark(int $id, string $status, ?string $error): void
    {
        Database::query(
            'UPDATE billing_webhook_events SET status = ?, last_error = ?, attempts = attempts + 1, processed_at = NOW() WHERE id = ?',
            [$status, $error, $id]
        );
    }
}
