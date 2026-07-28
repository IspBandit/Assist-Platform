<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Response;

final class InvoiceExportService
{
    public static function download(string $format): Response
    {
        $rows = self::sourceRows();
        $stamp = date('Ymd');

        if ($format === 'xero') {
            return CsvExport::download("assist-invoices-xero-{$stamp}.csv", self::xeroHeaders(), self::xeroRows($rows));
        }
        if ($format === 'myob') {
            return CsvExport::download("assist-invoices-myob-{$stamp}.csv", self::myobHeaders(), self::myobRows($rows));
        }

        return new Response('Unknown invoice export format.', 422);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recentInvoices(): array
    {
        return Database::select(
            'SELECT i.invoice_number,i.invoice_date,i.due_date,i.status,i.currency,i.total_cents,i.amount_paid_cents,'
            . 'COALESCE(i.business_name,bc.business_name,p.business_name,\'Unknown customer\') AS customer_name '
            . 'FROM invoices i LEFT JOIN billing_customers bc ON bc.id=i.billing_customer_id '
            . 'LEFT JOIN providers p ON p.id=i.provider_id ORDER BY COALESCE(i.invoice_date,DATE(i.created_at)) DESC,i.id DESC LIMIT 20'
        );
    }

    /** @return array<int,array<string,mixed>> */
    private static function sourceRows(): array
    {
        return Database::select(
            'SELECT i.*,COALESCE(i.business_name,bc.business_name,p.business_name,\'Unknown customer\') AS customer_name,'
            . 'COALESCE(bc.billing_email,p.public_email,p.email,\'\') AS customer_email,'
            . 'COALESCE(i.billing_address,bc.billing_address,\'\') AS customer_address,'
            . 'COALESCE(ii.description,\'Platform services\') AS line_description,'
            . 'COALESCE(ii.quantity,1) AS line_quantity,COALESCE(ii.amount_cents,i.subtotal_cents) AS line_amount_cents,'
            . 'COALESCE(ii.gst_cents,i.gst_cents) AS line_gst_cents '
            . 'FROM invoices i LEFT JOIN billing_customers bc ON bc.id=i.billing_customer_id '
            . 'LEFT JOIN providers p ON p.id=i.provider_id LEFT JOIN invoice_items ii ON ii.invoice_id=i.id '
            . "WHERE i.status<>'void' ORDER BY i.id,ii.id"
        );
    }

    /** @return array<int,string> */
    private static function xeroHeaders(): array
    {
        return ['ContactName','EmailAddress','POAddressLine1','POAddressLine2','POAddressLine3','POAddressLine4','POCity','PORegion','POPostalCode','POCountry','InvoiceNumber','Reference','InvoiceDate','DueDate','InventoryItemCode','Description','Quantity','UnitAmount','Discount','AccountCode','TaxType','TaxAmount','TrackingName1','TrackingOption1','TrackingName2','TrackingOption2','Currency','BrandingTheme'];
    }

    /** @param array<int,array<string,mixed>> $rows @return iterable<array<int,mixed>> */
    private static function xeroRows(array $rows): iterable
    {
        foreach ($rows as $row) {
            $quantity = max(1, (int) $row['line_quantity']);
            $exclusiveCents = (int) $row['gst_inclusive'] === 1
                ? (int) $row['line_amount_cents'] - (int) $row['line_gst_cents']
                : (int) $row['line_amount_cents'];
            yield [
                $row['customer_name'], $row['customer_email'], $row['customer_address'], '', '', '', '', '', '', 'Australia',
                $row['invoice_number'], $row['external_invoice_ref'] ?? '', self::date((string) ($row['invoice_date'] ?? '')),
                self::date((string) ($row['due_date'] ?? '')), '', $row['line_description'], $quantity,
                number_format($exclusiveCents / $quantity / 100, 2, '.', ''), '', '', '',
                number_format((int) $row['line_gst_cents'] / 100, 2, '.', ''), '', '', '', '', $row['currency'], '',
            ];
        }
    }

    /** @return array<int,string> */
    private static function myobHeaders(): array
    {
        return ['Co./Last Name','Email','Address 1','Invoice #','Date','Due Date','Customer PO','Description','Quantity','Ex-Tax Amount','GST Amount','Inc-Tax Amount','Currency','Status'];
    }

    /** @param array<int,array<string,mixed>> $rows @return iterable<array<int,mixed>> */
    private static function myobRows(array $rows): iterable
    {
        $previousInvoice = null;
        foreach ($rows as $row) {
            $invoice = (string) $row['invoice_number'];
            if ($previousInvoice !== null && $previousInvoice !== $invoice) {
                yield array_fill(0, count(self::myobHeaders()), '');
            }
            $exclusiveCents = (int) $row['gst_inclusive'] === 1
                ? (int) $row['line_amount_cents'] - (int) $row['line_gst_cents']
                : (int) $row['line_amount_cents'];
            $inclusiveCents = (int) $row['gst_inclusive'] === 1
                ? (int) $row['line_amount_cents']
                : (int) $row['line_amount_cents'] + (int) $row['line_gst_cents'];
            yield [
                $row['customer_name'], $row['customer_email'], $row['customer_address'], $invoice,
                self::date((string) ($row['invoice_date'] ?? '')), self::date((string) ($row['due_date'] ?? '')),
                $row['external_invoice_ref'] ?? '', $row['line_description'], max(1, (int) $row['line_quantity']),
                number_format($exclusiveCents / 100, 2, '.', ''), number_format((int) $row['line_gst_cents'] / 100, 2, '.', ''),
                number_format($inclusiveCents / 100, 2, '.', ''), $row['currency'], $row['status'],
            ];
            $previousInvoice = $invoice;
        }
    }

    private static function date(string $date): string
    {
        $timestamp = strtotime($date);
        return $timestamp === false ? '' : date('d/m/Y', $timestamp);
    }
}
