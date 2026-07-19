<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * The heart of the owner-finance ledger: posts balanced double-entry journals.
 *
 * Guarantees enforced here (in addition to the DB constraints):
 *  - every entry has >= 2 lines and total debits == total credits (> 0)
 *  - each line is debit XOR credit, both non-negative
 *  - accounts exist and are active; the target period is open/soft-locked
 *  - posting is idempotent on idempotency_key (a retry returns the same entry)
 *  - posted entries are immutable — corrections go through reverse()
 *
 * Amounts are handled internally as integer units of 1/10000 to avoid any float
 * drift, then stored as DECIMAL(19,4) strings.
 */
final class JournalPostingService
{
    private const SCALE = 10000;

    /**
     * Post (or, with status 'draft', stage) a journal entry.
     *
     * @param array{
     *   date:string, description?:string, lines:array<int,array<string,mixed>>,
     *   currency?:string, exchange_rate?:float, source_type?:?string, source_id?:?string,
     *   source_number?:?string, source_event_id?:?int, external_reference?:?string,
     *   provider_id?:?int, customer_id?:?int, subscription_id?:?int,
     *   idempotency_key?:?string, status?:string, posted_by?:?int
     * } $data
     * @return int journal entry id
     */
    public static function post(array $data): int
    {
        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $date = $data['date'] ?? date('Y-m-d');
        $status = $data['status'] ?? 'posted';
        $currency = strtoupper((string) ($data['currency'] ?? 'AUD'));
        $rate = (float) ($data['exchange_rate'] ?? 1);
        $idempotencyKey = $data['idempotency_key'] ?? null;

        // Idempotency: a repeated event must not create a second entry.
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Database::scalar(
                'SELECT id FROM owner_finance_journal_entries WHERE idempotency_key = ?',
                [$idempotencyKey]
            );
            if ($existing !== false && $existing !== null) {
                return (int) $existing;
            }
        }

        // Resolve + validate lines before opening a transaction.
        $prepared = [];
        $totalDebit = 0;
        $totalCredit = 0;
        $no = 0;
        foreach ($lines as $line) {
            $no++;
            $code = (string) ($line['account_code'] ?? '');
            $debitUnits = self::units($line['debit'] ?? 0);
            $creditUnits = self::units($line['credit'] ?? 0);

            if ($debitUnits < 0 || $creditUnits < 0) {
                throw new InvalidArgumentException('Journal amounts cannot be negative.');
            }
            if ($debitUnits > 0 && $creditUnits > 0) {
                throw new InvalidArgumentException('A line cannot have both a debit and a credit.');
            }
            if ($debitUnits === 0 && $creditUnits === 0) {
                throw new InvalidArgumentException('A line must have a debit or a credit.');
            }

            $accountId = ChartOfAccounts::requireId($code);
            $totalDebit += $debitUnits;
            $totalCredit += $creditUnits;

            $prepared[] = [
                'account_id' => $accountId,
                'account_code' => $code,
                'debit_units' => $debitUnits,
                'credit_units' => $creditUnits,
                'tax_code' => $line['tax_code'] ?? null,
                'tax_rate' => isset($line['tax_rate']) ? (float) $line['tax_rate'] : null,
                'tax_amount' => isset($line['tax_amount']) ? self::units($line['tax_amount']) : null,
                'description' => $line['description'] ?? null,
                'provider_id' => $line['provider_id'] ?? null,
                'customer_id' => $line['customer_id'] ?? null,
                'supplier_id' => $line['supplier_id'] ?? null,
                'subscription_id' => $line['subscription_id'] ?? null,
                'region_id' => $line['region_id'] ?? null,
                'service_category_id' => $line['service_category_id'] ?? null,
                'tracking_category' => $line['tracking_category'] ?? null,
                'cost_centre' => $line['cost_centre'] ?? null,
                'line_no' => $no,
            ];
        }

        if ($totalDebit !== $totalCredit) {
            throw new InvalidArgumentException('Journal does not balance: debits must equal credits.');
        }
        if ($totalDebit === 0) {
            throw new InvalidArgumentException('A journal entry cannot be zero value.');
        }

        // Period gate (only enforced for actually-posted entries; drafts may stage).
        $period = FinancialPeriodService::ensureForDate($date);
        if ($status === 'posted' && !FinancialPeriodService::isPostingAllowed($period)) {
            throw new RuntimeException('The financial period for ' . $date . ' is closed.');
        }

        Database::beginTransaction();
        try {
            $entryNumber = self::nextEntryNumber();
            $now = date('Y-m-d H:i:s');
            $isPosted = $status === 'posted';

            $entryId = Database::insert(
                'INSERT INTO owner_finance_journal_entries '
                . '(entry_number, transaction_date, posting_date, financial_year, period_id, description, '
                . 'source_type, source_id, source_number, source_event_id, external_reference, status, '
                . 'currency, exchange_rate, base_currency, total_debit, total_credit, provider_id, customer_id, '
                . 'subscription_id, idempotency_key, created_by, posted_by, created_at, posted_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $entryNumber,
                    $date,
                    $isPosted ? $date : null,
                    self::financialYear($date),
                    (int) $period['id'],
                    $data['description'] ?? null,
                    $data['source_type'] ?? null,
                    isset($data['source_id']) ? (string) $data['source_id'] : null,
                    $data['source_number'] ?? null,
                    $data['source_event_id'] ?? null,
                    $data['external_reference'] ?? null,
                    $status,
                    $currency,
                    $rate,
                    'AUD',
                    self::money($totalDebit),
                    self::money($totalCredit),
                    $data['provider_id'] ?? null,
                    $data['customer_id'] ?? null,
                    $data['subscription_id'] ?? null,
                    $idempotencyKey !== '' ? $idempotencyKey : null,
                    $data['posted_by'] ?? (\App\Auth\Auth::instance()->id()),
                    $isPosted ? ($data['posted_by'] ?? \App\Auth\Auth::instance()->id()) : null,
                    $now,
                    $isPosted ? $now : null,
                ]
            );

            foreach ($prepared as $line) {
                $baseDebit = (int) round($line['debit_units'] * $rate);
                $baseCredit = (int) round($line['credit_units'] * $rate);
                Database::query(
                    'INSERT INTO owner_finance_journal_lines '
                    . '(entry_id, account_id, account_code, debit, credit, base_debit, base_credit, tax_code, tax_rate, '
                    . 'tax_amount, description, provider_id, customer_id, supplier_id, subscription_id, region_id, '
                    . 'service_category_id, tracking_category, cost_centre, line_no, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $entryId,
                        $line['account_id'],
                        $line['account_code'],
                        self::money($line['debit_units']),
                        self::money($line['credit_units']),
                        self::money($baseDebit),
                        self::money($baseCredit),
                        $line['tax_code'],
                        $line['tax_rate'],
                        $line['tax_amount'] !== null ? self::money($line['tax_amount']) : null,
                        $line['description'],
                        $line['provider_id'],
                        $line['customer_id'],
                        $line['supplier_id'],
                        $line['subscription_id'],
                        $line['region_id'],
                        $line['service_category_id'],
                        $line['tracking_category'],
                        $line['cost_centre'],
                        $line['line_no'],
                    ]
                );
            }

            Database::commit();
            return $entryId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Reverse a posted entry by creating an equal-and-opposite entry dated today
     * (or $date) and marking the original 'reversed'. Returns the reversal id.
     */
    public static function reverse(int $entryId, string $reason, ?string $date = null): int
    {
        $entry = Database::selectOne('SELECT * FROM owner_finance_journal_entries WHERE id = ?', [$entryId]);
        if ($entry === null) {
            throw new RuntimeException('Journal entry not found.');
        }
        if ((string) $entry['status'] !== 'posted') {
            throw new RuntimeException('Only a posted entry can be reversed.');
        }

        $lines = Database::select('SELECT * FROM owner_finance_journal_lines WHERE entry_id = ? ORDER BY line_no', [$entryId]);
        $reversalLines = [];
        foreach ($lines as $line) {
            $reversalLines[] = [
                'account_code' => (string) $line['account_code'],
                // swap debit/credit
                'debit' => (float) $line['credit'],
                'credit' => (float) $line['debit'],
                'tax_code' => $line['tax_code'],
                'description' => 'Reversal: ' . (string) ($line['description'] ?? ''),
                'provider_id' => $line['provider_id'],
                'customer_id' => $line['customer_id'],
                'supplier_id' => $line['supplier_id'],
                'subscription_id' => $line['subscription_id'],
            ];
        }

        $reversalId = self::post([
            'date' => $date ?? date('Y-m-d'),
            'description' => 'Reversal of ' . (string) $entry['entry_number'] . ' — ' . $reason,
            'lines' => $reversalLines,
            'source_type' => 'reversal',
            'source_id' => (string) $entryId,
            'source_number' => (string) $entry['entry_number'],
            'provider_id' => $entry['provider_id'],
            'customer_id' => $entry['customer_id'],
            'subscription_id' => $entry['subscription_id'],
            'currency' => (string) $entry['currency'],
            'exchange_rate' => (float) $entry['exchange_rate'],
        ]);

        Database::query(
            'UPDATE owner_finance_journal_entries SET reversal_of_entry_id = ? WHERE id = ?',
            [$entryId, $reversalId]
        );
        Database::query(
            "UPDATE owner_finance_journal_entries SET status = 'reversed', reversed_at = NOW(), reversed_by = ? WHERE id = ?",
            [\App\Auth\Auth::instance()->id(), $entryId]
        );

        FinanceAudit::record('journal.reverse', 'journal_entry', (string) $entryId, null, ['reversal_id' => $reversalId], $reason);
        return $reversalId;
    }

    /** Next sequential entry number, e.g. JE-000123. */
    private static function nextEntryNumber(): string
    {
        $max = (int) Database::scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(entry_number, 4) AS UNSIGNED)), 0) "
            . "FROM owner_finance_journal_entries WHERE entry_number LIKE 'JE-%'"
        );
        return 'JE-' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private static function financialYear(string $date): int
    {
        // Australian FY starts in July; FY label is the year it ends in.
        $ts = strtotime($date) ?: time();
        $y = (int) date('Y', $ts);
        $m = (int) date('n', $ts);
        return $m >= 7 ? $y + 1 : $y;
    }

    /** Convert a dollar amount (float/string) into integer units of 1/10000. */
    private static function units(mixed $amount): int
    {
        return (int) round(((float) $amount) * self::SCALE);
    }

    /** Format integer units back to a DECIMAL(19,4) string. */
    private static function money(int $units): string
    {
        return number_format($units / self::SCALE, 4, '.', '');
    }
}
