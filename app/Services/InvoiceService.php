<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Builds Australian-style tax-invoice records (AUD, GST, ABN). Amounts are in
 * integer cents. Subscription billing is kept separate from booking/commission
 * accounting.
 *
 * NOTE: invoice formatting and GST treatment are MARKED FOR ACCOUNTANT REVIEW.
 * These records do not assert compliance with tax obligations until reviewed.
 */
final class InvoiceService
{
    /**
     * Create an invoice with line items. GST is computed from tax_settings.
     *
     * @param array<int,array{description:string,quantity?:int,unit_amount_cents:int}> $items
     */
    public function createInvoice(int $providerId, array $items, array $options = []): int
    {
        $gstRate = (float) $this->taxSetting('gst_rate', '10');
        $gstRegistered = $this->taxSetting('gst_registered', '0') === '1';
        $gstInclusive = (bool) ($options['gst_inclusive'] ?? ($this->taxSetting('gst_inclusive_default', '1') === '1'));

        $subtotal = 0;
        $gstTotal = 0;
        $prepared = [];

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $lineAmount = (int) $item['unit_amount_cents'] * $qty;
            $lineGst = 0;
            if ($gstRegistered && $gstRate > 0) {
                $lineGst = $gstInclusive
                    ? (int) round($lineAmount - ($lineAmount / (1 + $gstRate / 100)))
                    : (int) round($lineAmount * ($gstRate / 100));
            }
            $subtotal += $gstInclusive ? ($lineAmount - $lineGst) : $lineAmount;
            $gstTotal += $lineGst;
            $prepared[] = [$item['description'], $qty, (int) $item['unit_amount_cents'], $lineAmount, $lineGst];
        }

        $total = $subtotal + $gstTotal;

        $customer = Database::selectOne('SELECT id, business_name, billing_address, abn FROM billing_customers WHERE provider_id = ?', [$providerId]);

        $invoiceId = Database::insert(
            'INSERT INTO invoices '
            . '(invoice_number, billing_customer_id, provider_id, subscription_id, invoice_date, due_date, status, currency, gst_inclusive, '
            . 'subtotal_cents, gst_cents, total_cents, amount_paid_cents, business_name, billing_address, abn, notes, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW(), NOW())',
            [
                $this->nextInvoiceNumber(),
                $customer['id'] ?? null,
                $providerId,
                $options['subscription_id'] ?? null,
                $options['due_date'] ?? null,
                $options['status'] ?? 'open',
                (string) $this->taxSetting('currency', 'AUD'),
                $gstInclusive ? 1 : 0,
                $subtotal,
                $gstTotal,
                $total,
                $customer['business_name'] ?? null,
                $customer['billing_address'] ?? null,
                $customer['abn'] ?? null,
                $options['notes'] ?? null,
            ]
        );

        foreach ($prepared as [$description, $qty, $unit, $lineAmount, $lineGst]) {
            Database::query(
                'INSERT INTO invoice_items (invoice_id, description, quantity, unit_amount_cents, amount_cents, gst_cents, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$invoiceId, $description, $qty, $unit, $lineAmount, $lineGst]
            );
        }

        AuditLog::record('billing.invoice_created', 'invoice', (string) $invoiceId, null, (string) $total);
        return $invoiceId;
    }

    /** Record a payment against an invoice (card via gateway, or offline/bank). */
    public function recordPayment(int $invoiceId, int $amountCents, string $method = 'offline', ?string $externalRef = null, ?int $recordedBy = null): int
    {
        $invoice = Database::selectOne('SELECT provider_id, total_cents, amount_paid_cents FROM invoices WHERE id = ?', [$invoiceId]);
        if ($invoice === null) {
            return 0;
        }

        $paymentId = Database::insert(
            'INSERT INTO payments (invoice_id, provider_id, amount_cents, currency, status, method, external_payment_ref, paid_at, recorded_by, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())',
            [$invoiceId, $invoice['provider_id'], $amountCents, 'AUD', 'succeeded', $method, $externalRef, $recordedBy]
        );

        $paid = (int) $invoice['amount_paid_cents'] + $amountCents;
        $status = $paid >= (int) $invoice['total_cents'] ? 'paid' : 'open';
        Database::query(
            'UPDATE invoices SET amount_paid_cents = ?, status = ?, updated_at = NOW() WHERE id = ?',
            [$paid, $status, $invoiceId]
        );

        AuditLog::record('billing.payment_recorded', 'invoice', (string) $invoiceId, $method, (string) $amountCents);
        return $paymentId;
    }

    /** Record (or issue) a refund against a payment. */
    public function recordRefund(int $paymentId, int $amountCents, string $reason = '', ?int $createdBy = null): int
    {
        $refundId = Database::insert(
            'INSERT INTO refunds (payment_id, amount_cents, reason, status, created_by, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())',
            [$paymentId, $amountCents, $reason !== '' ? $reason : null, 'succeeded', $createdBy]
        );
        Database::query('UPDATE payments SET status = ? WHERE id = ?', ['refunded', $paymentId]);
        AuditLog::record('billing.refund_recorded', 'payment', (string) $paymentId, $reason, (string) $amountCents);
        return $refundId;
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = (string) $this->taxSetting('invoice_prefix', 'VA-');
        $next = (int) $this->taxSetting('next_invoice_number', '1000');
        Database::query(
            'UPDATE tax_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?',
            [(string) ($next + 1), 'next_invoice_number']
        );
        return $prefix . $next;
    }

    private function taxSetting(string $key, string $default): string
    {
        $value = Database::scalar('SELECT setting_value FROM tax_settings WHERE setting_key = ?', [$key]);
        return $value === null || $value === false ? $default : (string) $value;
    }
}
