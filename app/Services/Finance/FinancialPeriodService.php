<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;

/**
 * Monthly financial periods keyed YYYY-MM. Posting is permitted into open and
 * soft-locked periods (soft-locked only by privileged callers); closed periods
 * reject ordinary posting. Reopening is audited by the caller.
 */
final class FinancialPeriodService
{
    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::select(
            'SELECT * FROM owner_finance_financial_periods ORDER BY start_date DESC'
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM owner_finance_financial_periods WHERE id = ?', [$id]);
    }

    /**
     * Return the period containing $date, creating the monthly period on demand
     * (open) if it does not yet exist.
     *
     * @return array<string,mixed>
     */
    public static function ensureForDate(string $date): array
    {
        $ts = strtotime($date) ?: time();
        $code = date('Y-m', $ts);

        $existing = Database::selectOne(
            'SELECT * FROM owner_finance_financial_periods WHERE period_code = ?',
            [$code]
        );
        if ($existing !== null) {
            return $existing;
        }

        Database::query(
            'INSERT IGNORE INTO owner_finance_financial_periods '
            . "(period_code, label, start_date, end_date, status, created_at, updated_at) "
            . "VALUES (?, ?, ?, ?, 'open', NOW(), NOW())",
            [$code, date('F Y', $ts), date('Y-m-01', $ts), date('Y-m-t', $ts)]
        );

        /** @var array<string,mixed> $row */
        $row = Database::selectOne(
            'SELECT * FROM owner_finance_financial_periods WHERE period_code = ?',
            [$code]
        );
        return $row;
    }

    public static function isPostingAllowed(array $period): bool
    {
        return in_array((string) $period['status'], ['open', 'soft_locked'], true);
    }

    public static function close(int $id, ?int $userId): bool
    {
        $period = self::find($id);
        if ($period === null || (string) $period['status'] === 'closed') {
            return false;
        }
        Database::query(
            "UPDATE owner_finance_financial_periods SET status = 'closed', locked_at = NOW(), locked_by = ?, updated_at = NOW() WHERE id = ?",
            [$userId, $id]
        );
        return true;
    }

    public static function reopen(int $id, ?int $userId, string $reason): bool
    {
        $period = self::find($id);
        if ($period === null || (string) $period['status'] !== 'closed') {
            return false;
        }
        Database::query(
            "UPDATE owner_finance_financial_periods SET status = 'open', reopened_at = NOW(), reopened_by = ?, reopen_reason = ?, updated_at = NOW() WHERE id = ?",
            [$userId, $reason, $id]
        );
        return true;
    }
}
