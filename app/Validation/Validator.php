<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Small rule-based validator. Rules are pipe-delimited strings, e.g.
 *   'required|email|max:190'
 * Supported: required, email, url, numeric, min:n, max:n, confirmed,
 *            accepted, in:a,b,c, matches:field
 */
final class Validator
{
    private array $errors = [];

    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $name, $param);
            }
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $name, ?string $param): void
    {
        if (isset($this->errors[$field])) {
            return; // first error per field
        }
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

        switch ($name) {
            case 'required':
                if ($value === null || $value === '' || $value === []) {
                    $this->errors[$field] = "{$label} is required.";
                }
                break;
            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "{$label} must be a valid email address.";
                }
                break;
            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$field] = "{$label} must be a valid URL.";
                }
                break;
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->errors[$field] = "{$label} must be a number.";
                }
                break;
            case 'min':
                if ($value !== null && $value !== '' && mb_strlen((string) $value) < (int) $param) {
                    $this->errors[$field] = "{$label} must be at least {$param} characters.";
                }
                break;
            case 'max':
                if ($value !== null && mb_strlen((string) $value) > (int) $param) {
                    $this->errors[$field] = "{$label} must not exceed {$param} characters.";
                }
                break;
            case 'accepted':
                if (!in_array($value, ['1', 'on', 'true', true, 1], true)) {
                    $this->errors[$field] = "{$label} must be accepted.";
                }
                break;
            case 'in':
                $options = explode(',', (string) $param);
                if ($value !== null && $value !== '' && !in_array((string) $value, $options, true)) {
                    $this->errors[$field] = "{$label} is invalid.";
                }
                break;
            case 'matches':
                if ($value !== ($this->data[$param] ?? null)) {
                    $this->errors[$field] = "{$label} does not match.";
                }
                break;
            case 'confirmed':
                if ($value !== ($this->data[$field . '_confirmation'] ?? null)) {
                    $this->errors[$field] = "{$label} confirmation does not match.";
                }
                break;
        }
    }
}
