<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;

/**
 * Read/write access to the owner-finance chart of accounts.
 *
 * System accounts (control accounts such as Accounts Receivable, Provider Funds
 * Held, GST Control) are protected: they cannot be deleted and their type / code
 * cannot be changed once seeded.
 */
final class ChartOfAccounts
{
    public const TYPES = [
        'asset'          => 'Asset',
        'liability'      => 'Liability',
        'equity'         => 'Equity',
        'income'         => 'Income',
        'cost_of_sales'  => 'Cost of Sales',
        'expense'        => 'Expense',
        'other_income'   => 'Other Income',
        'other_expense'  => 'Other Expense',
    ];

    /** Account types whose natural balance is a debit. */
    public const DEBIT_TYPES = ['asset', 'cost_of_sales', 'expense', 'other_expense'];

    /** @return array<int,array<string,mixed>> */
    public static function all(bool $includeArchived = true): array
    {
        $sql = 'SELECT * FROM owner_finance_accounts';
        if (!$includeArchived) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY code';
        return Database::select($sql);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM owner_finance_accounts WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByCode(string $code): ?array
    {
        return Database::selectOne('SELECT * FROM owner_finance_accounts WHERE code = ?', [$code]);
    }

    /** Resolve an account id from its code, or throw if missing/inactive. */
    public static function requireId(string $code): int
    {
        $row = self::findByCode($code);
        if ($row === null) {
            throw new \RuntimeException("Unknown account code: {$code}");
        }
        if ((int) $row['is_active'] !== 1) {
            throw new \RuntimeException("Account {$code} is archived and cannot be posted to.");
        }
        return (int) $row['id'];
    }

    public static function create(string $code, string $name, string $type, ?string $defaultTaxCode = null): int
    {
        return Database::insert(
            'INSERT INTO owner_finance_accounts (code, name, type, default_tax_code, is_system, is_active, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, 0, 1, NOW(), NOW())',
            [$code, $name, $type, $defaultTaxCode]
        );
    }

    public static function update(int $id, string $name, string $type, ?string $defaultTaxCode = null): void
    {
        $row = self::find($id);
        if ($row === null) {
            return;
        }
        // System accounts may be renamed but not retyped (control logic relies on type).
        $type = (int) $row['is_system'] === 1 ? (string) $row['type'] : $type;
        Database::query(
            'UPDATE owner_finance_accounts SET name = ?, type = ?, default_tax_code = ?, updated_at = NOW() WHERE id = ?',
            [$name, $type, $defaultTaxCode, $id]
        );
    }

    public static function setActive(int $id, bool $active): void
    {
        $row = self::find($id);
        if ($row === null) {
            return;
        }
        // A system account cannot be archived; an account with postings cannot be archived away silently.
        if (!$active && (int) $row['is_system'] === 1) {
            return;
        }
        Database::query(
            'UPDATE owner_finance_accounts SET is_active = ?, updated_at = NOW() WHERE id = ?',
            [$active ? 1 : 0, $id]
        );
    }

    public static function hasPostings(int $id): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM owner_finance_journal_lines WHERE account_id = ?',
            [$id]
        ) > 0;
    }
}
