<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Side-by-side comparison helpers for up to four RV model cards.
 */
final class ComparisonService
{
    public const MAX_MODELS = 4;

    /**
     * @param array<int,array<string,mixed>> $models
     * @return array{
     *   models:array<int,array<string,mixed>>,
     *   rows:array<int,array{key:string,label:string,values:array<int,string|null>,differs:bool}>,
     *   winners:array<string,string|null>
     * }
     */
    public function build(array $models): array
    {
        $models = array_slice(array_values($models), 0, self::MAX_MODELS);
        $fields = [
            ['key' => 'manufacturer_name', 'label' => 'Manufacturer'],
            ['key' => 'category_label', 'label' => 'Category'],
            ['key' => 'production_status', 'label' => 'Production status'],
            ['key' => 'verification_status', 'label' => 'Verification'],
            ['key' => 'sleeps', 'label' => 'Sleeps'],
            ['key' => 'body_length_m', 'label' => 'Body length (m)'],
            ['key' => 'tare_kg', 'label' => 'Tare (kg)'],
            ['key' => 'atm_kg', 'label' => 'ATM (kg)'],
            ['key' => 'payload_kg', 'label' => 'Payload (kg)'],
            ['key' => 'price_label', 'label' => 'Price'],
        ];

        $rows = [];
        foreach ($fields as $field) {
            $values = [];
            foreach ($models as $model) {
                $raw = $model[$field['key']] ?? null;
                if ($raw === null || $raw === '') {
                    $values[] = null;
                } else {
                    $values[] = is_float($raw) ? number_format($raw, 2) : (string) $raw;
                }
            }
            $present = array_values(array_filter($values, static fn (?string $v): bool => $v !== null));
            $differs = count(array_unique($present)) > 1;
            $rows[] = [
                'key' => $field['key'],
                'label' => $field['label'],
                'values' => $values,
                'differs' => $differs,
            ];
        }

        return [
            'models' => $models,
            'rows' => $rows,
            'winners' => [
                'lightest_tare' => $this->winner($models, 'tare_kg', true),
                'highest_payload' => $this->winner($models, 'payload_kg', false),
                'shortest' => $this->winner($models, 'body_length_m', true),
                'lowest_price' => $this->winner($models, 'price_aud_cents', true),
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $models
     */
    private function winner(array $models, string $key, bool $preferLower): ?string
    {
        $bestIndex = null;
        $bestValue = null;
        foreach ($models as $index => $model) {
            if (!isset($model[$key]) || !is_numeric($model[$key])) {
                continue;
            }
            $value = (float) $model[$key];
            if ($bestValue === null
                || ($preferLower && $value < $bestValue)
                || (!$preferLower && $value > $bestValue)
            ) {
                $bestValue = $value;
                $bestIndex = $index;
            }
        }
        if ($bestIndex === null) {
            return null;
        }
        return (string) ($models[$bestIndex]['name'] ?? ('Model ' . ($bestIndex + 1)));
    }
}
