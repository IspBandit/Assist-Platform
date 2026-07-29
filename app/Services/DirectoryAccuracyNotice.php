<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * Fixed, factual-only provider record notice.
 *
 * This content must not contain offers, pricing, benefits, testimonials,
 * provider-claim invitations or links to commercial pages. Keeping the copy
 * server-owned prevents an editable broadcast from being misclassified as a
 * factual directory notice.
 */
final class DirectoryAccuracyNotice
{
    public static function subject(?string $brandName = null): string
    {
        $brandName = trim((string) $brandName) ?: current_brand()->name();
        return 'Please check the business information ' . $brandName . ' currently displays';
    }

    public static function previewBody(?string $brandName = null): string
    {
        $brandName = self::e(trim((string) $brandName) ?: current_brand()->name());
        return '<p><strong>Fixed factual notice.</strong> Each recipient receives the business name, locality, '
            . 'currently displayed services and public-source record held by ' . $brandName . ', with instructions to reply '
            . 'CONFIRM, CORRECT or REMOVE. Promotional copy and commercial links are not permitted.</p>';
    }

    /** @param array<string,mixed> $provider */
    public static function html(array $provider): string
    {
        $brand = current_brand();
        $brandName = $brand->name();
        $contact = $brand->contact();
        $support = trim((string) ($contact['support_email'] ?? ''));
        $legalName = $brand->legalName();
        $abn = trim((string) Config::get('billing.abn', ''));
        $stopUrl = EmailSuppression::directoryNoticeOptOutUrl((string) $provider['email']);
        $fields = [
            'Business name' => (string) ($provider['business_name'] ?? ''),
            'Locality' => trim((string) ($provider['town_name'] ?? '') . (!empty($provider['state_abbr']) ? ', ' . $provider['state_abbr'] : '')),
            'Services currently recorded' => (string) ($provider['services'] ?? 'No service labels recorded'),
            'Public phone currently recorded' => (string) ($provider['public_phone'] ?? ''),
            'Public website currently recorded' => (string) ($provider['website'] ?? ''),
        ];

        $rows = '';
        foreach ($fields as $label => $value) {
            if (trim($value) === '') {
                continue;
            }
            $rows .= '<tr><th style="text-align:left;padding:8px;border-bottom:1px solid #e3e0d8;vertical-align:top">'
                . self::e($label) . '</th><td style="padding:8px;border-bottom:1px solid #e3e0d8">' . self::e($value) . '</td></tr>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:0 auto;color:#20282d">'
            . '<div style="border-left:4px solid #0f7774;padding:4px 0 4px 14px;margin-bottom:24px"><strong style="font-size:22px">' . self::e($brandName) . '</strong><br>'
            . '<span style="font-size:12px;color:#626b70">Australian traveller service directory</span></div>'
            . '<p>Hello,</p><p>This is a factual directory-accuracy notice. ' . self::e($brandName) . ' currently displays a public, unclaimed record for the business below. '
            . '“Unclaimed” means no authorised representative has verified the record with ' . self::e($brandName) . '.</p>'
            . '<table style="border-collapse:collapse;width:100%;margin:18px 0">' . $rows . '</table>'
            . '<p>Please reply to <strong>' . self::e($support) . '</strong> with one of:</p>'
            . '<ul><li><strong>CONFIRM</strong> — the displayed details appear correct;</li>'
            . '<li><strong>CORRECT</strong> — followed by the factual changes required; or</li>'
            . '<li><strong>REMOVE</strong> — if this public record should be removed.</li></ul>'
            . '<p>This notice does not subscribe this address to marketing, does not offer a paid product and contains no promotional link.</p>'
            . '<hr style="border:none;border-top:1px solid #e3e0d8;margin:24px 0">'
            . '<p style="font-size:12px;color:#626b70">Sent by ' . self::e($legalName)
            . ($abn !== '' ? ', ABN ' . self::e($abn) : '')
            . ($support !== '' ? '. Contact: ' . self::e($support) : '') . '.</p>'
            . '<p style="font-size:12px;color:#626b70"><a href="' . self::e($stopUrl) . '">Stop future directory-accuracy notices to this address</a>.</p></div>';
    }

    /** @param array<string,mixed> $provider */
    public static function text(array $provider): string
    {
        $brand = current_brand();
        $brandName = $brand->name();
        $support = trim((string) ($brand->contact()['support_email'] ?? ''));
        $abn = trim((string) Config::get('billing.abn', ''));
        $lines = [
            $brandName . ' — factual directory-accuracy notice',
            '',
            $brandName . ' currently displays a public, unclaimed record for this business. “Unclaimed” means no authorised representative has verified the record with ' . $brandName . '.',
            '',
            'Business name: ' . (string) ($provider['business_name'] ?? ''),
            'Locality: ' . trim((string) ($provider['town_name'] ?? '') . (!empty($provider['state_abbr']) ? ', ' . $provider['state_abbr'] : '')),
            'Services currently recorded: ' . (string) ($provider['services'] ?? 'No service labels recorded'),
        ];
        if (!empty($provider['public_phone'])) {
            $lines[] = 'Public phone currently recorded: ' . $provider['public_phone'];
        }
        if (!empty($provider['website'])) {
            $lines[] = 'Public website currently recorded: ' . $provider['website'];
        }
        $lines = array_merge($lines, [
            '',
            'Reply to ' . $support . ' with CONFIRM, CORRECT followed by the required factual changes, or REMOVE.',
            '',
            'This notice does not subscribe this address to marketing, does not offer a paid product and contains no promotional link.',
            '',
            'Sent by ' . $brand->legalName() . ($abn !== '' ? ', ABN ' . $abn : '') . '. Contact: ' . $support . '.',
            'Stop future directory-accuracy notices: ' . EmailSuppression::directoryNoticeOptOutUrl((string) $provider['email']),
        ]);
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $notification */
    public static function assertFixed(array $notification): void
    {
        if ((string) ($notification['campaign_type'] ?? '') !== 'directory_accuracy'
            || (string) ($notification['title'] ?? '') !== self::subject()
            || (string) ($notification['body'] ?? '') !== self::previewBody()) {
            throw new RuntimeException('Directory-accuracy notices must use the fixed factual-only subject and content.');
        }
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
