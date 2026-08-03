<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Deterministic brochure / catalogue text → draft field hints.
 * No AI calls. Low confidence by design; human review required.
 */
final class BrochureTextExtractor
{
    public const VERSION = 'polaris-brochure-text-1';

    /**
     * @return array{payload:array<string,mixed>,confidence:int,hints:list<string>,errors:list<string>}
     */
    public static function extract(string $text, ?string $defaultManufacturer = null): array
    {
        $raw = trim($text);
        $hints = [];
        $errors = [];
        if ($raw === '') {
            return [
                'payload' => [],
                'confidence' => 0,
                'hints' => [],
                'errors' => ['Empty brochure text.'],
            ];
        }

        $payload = [
            'manufacturer_name' => $defaultManufacturer ?? '',
            'model_name' => '',
            'variant_name' => 'Standard',
            'category' => 'caravan',
            'sleeps' => null,
            'tare_kg' => null,
            'atm_kg' => null,
            'body_length_m' => null,
            'fresh_water_l' => null,
            'solar_w' => null,
            'bathroom_type' => null,
            'price_aud' => null,
            'price_status' => 'from',
            'description' => mb_substr(preg_replace('/\s+/', ' ', $raw) ?? $raw, 0, 400),
            'extractor' => self::VERSION,
        ];

        if (preg_match('/\b(manufacturer|brand)\s*[:\-]\s*([^\n\r,]{2,80})/i', $raw, $m)) {
            $payload['manufacturer_name'] = trim($m[2]);
            $hints[] = 'Manufacturer label found.';
        } elseif ($defaultManufacturer !== null && $defaultManufacturer !== '') {
            $payload['manufacturer_name'] = $defaultManufacturer;
            $hints[] = 'Manufacturer taken from upload form.';
        }

        if (preg_match('/\b(model|range)\s*[:\-]\s*([^\n\r,]{2,80})/i', $raw, $m)) {
            $payload['model_name'] = trim($m[2]);
            $hints[] = 'Model label found.';
        } elseif (preg_match('/\b([A-Z][A-Za-z0-9 \-]{2,40})\s+(caravan|hybrid|camper|motorhome)\b/i', $raw, $m)) {
            $payload['model_name'] = trim($m[1]);
            $hints[] = 'Model inferred from category phrase.';
        }

        if (preg_match('/\bhybrid\b/i', $raw)) {
            $payload['category'] = 'hybrid_caravan';
            $hints[] = 'Category hybrid.';
        } elseif (preg_match('/\bmotorhome\b/i', $raw)) {
            $payload['category'] = 'motorhome';
            $hints[] = 'Category motorhome.';
        } elseif (preg_match('/\bcamper\s*van\b/i', $raw)) {
            $payload['category'] = 'campervan';
            $hints[] = 'Category campervan.';
        } elseif (preg_match('/\bcamper\s*trailer\b/i', $raw)) {
            $payload['category'] = 'camper_trailer';
            $hints[] = 'Category camper trailer.';
        } elseif (preg_match('/\bslide[- ]?on\b/i', $raw)) {
            $payload['category'] = 'slide_on';
            $hints[] = 'Category slide-on.';
        }

        if (preg_match('/\b(?:sleeps|berth[s]?)\s*[:\-]?\s*(\d{1,2})\b/i', $raw, $m)) {
            $payload['sleeps'] = (int) $m[1];
            $hints[] = 'Sleeps detected.';
        }
        if (preg_match('/\b(?:tare|unladen)\s*(?:mass|weight)?\s*[:\-]?\s*(\d{3,4})\s*kg\b/i', $raw, $m)) {
            $payload['tare_kg'] = (int) $m[1];
            $hints[] = 'Tare detected.';
        }
        if (preg_match('/\bATM\s*[:\-]?\s*(\d{3,4})\s*kg\b/i', $raw, $m)) {
            $payload['atm_kg'] = (int) $m[1];
            $hints[] = 'ATM detected.';
        }
        if (preg_match('/\b(?:body\s*)?length\s*[:\-]?\s*(\d+(?:\.\d+)?)\s*m\b/i', $raw, $m)) {
            $payload['body_length_m'] = (float) $m[1];
            $hints[] = 'Length detected.';
        }
        if (preg_match('/\bfresh\s*water\s*[:\-]?\s*(\d{2,4})\s*L\b/i', $raw, $m)) {
            $payload['fresh_water_l'] = (int) $m[1];
            $hints[] = 'Fresh water detected.';
        }
        if (preg_match('/\bsolar\s*[:\-]?\s*(\d{2,4})\s*W\b/i', $raw, $m)) {
            $payload['solar_w'] = (int) $m[1];
            $hints[] = 'Solar detected.';
        }
        if (preg_match('/\b(ensuite|bathroom|toilet)\b/i', $raw)) {
            $payload['bathroom_type'] = 'ensuite';
            $hints[] = 'Bathroom/bathroom mentioned.';
        }
        if (preg_match('/\$\s*(\d{1,3}(?:,\d{3})+|\d{4,7})\b/', $raw, $m)) {
            $payload['price_aud'] = (int) str_replace(',', '', $m[1]);
            $hints[] = 'Price figure detected.';
        }

        if ($payload['manufacturer_name'] === '' || $payload['model_name'] === '') {
            $errors[] = 'Manufacturer and model names are required before review can publish.';
        }

        $confidence = min(85, 20 + (count($hints) * 8));
        if ($errors !== []) {
            $confidence = min($confidence, 45);
        }

        return [
            'payload' => $payload,
            'confidence' => $confidence,
            'hints' => $hints,
            'errors' => $errors,
        ];
    }

    /**
     * Best-effort literal string scrape from a PDF binary (no OCR).
     */
    public static function extractTextFromPdf(string $binary): string
    {
        if ($binary === '' || !str_starts_with($binary, '%PDF')) {
            return '';
        }
        $chunks = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,200}\)/', $binary, $matches)) {
            foreach ($matches[0] as $literal) {
                $inner = substr($literal, 1, -1);
                $inner = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $inner);
                $inner = preg_replace('/\\\\[0-7]{1,3}/', '', $inner) ?? $inner;
                if (preg_match('/[A-Za-z]{3,}/', $inner)) {
                    $chunks[] = $inner;
                }
            }
        }
        return trim(implode("\n", array_slice($chunks, 0, 400)));
    }
}
