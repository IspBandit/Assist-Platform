<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;

/**
 * Read-only reporting helpers built directly from POSTED journal lines, so every
 * figure reconciles to the general ledger. Foundation set: trial balance plus a
 * handful of dashboard aggregates. Richer reports arrive in later increments.
 */
final class FinanceReport
{
    /**
     * Trial balance as at $asOf (inclusive). Returns one row per account that has
     * activity, with a net debit/credit, plus grand totals that must be equal.
     *
     * @return array{rows:array<int,array<string,mixed>>, total_debit:float, total_credit:float, balanced:bool}
     */
    public static function trialBalance(?string $asOf = null): array
    {
        $params = [];
        $dateClause = '';
        if ($asOf !== null) {
            $dateClause = ' AND e.transaction_date <= ?';
            $params[] = $asOf;
        }

        $rows = Database::select(
            'SELECT a.code, a.name, a.type, '
            . 'COALESCE(SUM(l.debit), 0) AS debit, COALESCE(SUM(l.credit), 0) AS credit '
            . 'FROM owner_finance_accounts a '
            . 'JOIN owner_finance_journal_lines l ON l.account_id = a.id '
            . "JOIN owner_finance_journal_entries e ON e.id = l.entry_id AND e.status = 'posted'" . $dateClause . ' '
            . 'GROUP BY a.id, a.code, a.name, a.type '
            . 'HAVING debit <> 0 OR credit <> 0 '
            . 'ORDER BY a.code',
            $params
        );

        $out = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($rows as $r) {
            $net = (float) $r['debit'] - (float) $r['credit'];
            $debitCol = $net > 0 ? $net : 0.0;
            $creditCol = $net < 0 ? -$net : 0.0;
            $totalDebit += $debitCol;
            $totalCredit += $creditCol;
            $out[] = [
                'code' => $r['code'],
                'name' => $r['name'],
                'type' => $r['type'],
                'debit' => $debitCol,
                'credit' => $creditCol,
            ];
        }

        return [
            'rows' => $out,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'balanced' => round($totalDebit, 2) === round($totalCredit, 2),
        ];
    }

    /** Net balance of a single account (posted only), signed for its natural side. */
    public static function accountBalance(string $code, ?string $from = null, ?string $to = null): float
    {
        // Date placeholders sit in the JOIN clause (before the WHERE), so they
        // must come first in the bound-parameter list; the code comes last.
        $params = [];
        $clause = '';
        if ($from !== null) { $clause .= ' AND e.transaction_date >= ?'; $params[] = $from; }
        if ($to !== null) { $clause .= ' AND e.transaction_date <= ?'; $params[] = $to; }
        $params[] = $code;

        $row = Database::selectOne(
            'SELECT a.type, COALESCE(SUM(l.debit),0) AS debit, COALESCE(SUM(l.credit),0) AS credit '
            . 'FROM owner_finance_accounts a '
            . 'JOIN owner_finance_journal_lines l ON l.account_id = a.id '
            . "JOIN owner_finance_journal_entries e ON e.id = l.entry_id AND e.status = 'posted'" . $clause . ' '
            . 'WHERE a.code = ? GROUP BY a.id, a.type',
            $params
        );
        if ($row === null) {
            return 0.0;
        }
        $net = (float) $row['debit'] - (float) $row['credit'];
        return in_array((string) $row['type'], ChartOfAccounts::DEBIT_TYPES, true) ? round($net, 2) : round(-$net, 2);
    }

    /** Sum of posted activity for an account type within a date range. */
    public static function typeTotal(string $type, ?string $from = null, ?string $to = null): float
    {
        $params = [];
        $clause = '';
        if ($from !== null) { $clause .= ' AND e.transaction_date >= ?'; $params[] = $from; }
        if ($to !== null) { $clause .= ' AND e.transaction_date <= ?'; $params[] = $to; }
        $params[] = $type;

        $row = Database::selectOne(
            'SELECT COALESCE(SUM(l.debit),0) AS debit, COALESCE(SUM(l.credit),0) AS credit '
            . 'FROM owner_finance_accounts a '
            . 'JOIN owner_finance_journal_lines l ON l.account_id = a.id '
            . "JOIN owner_finance_journal_entries e ON e.id = l.entry_id AND e.status = 'posted'" . $clause . ' '
            . 'WHERE a.type = ?',
            $params
        );
        if ($row === null) {
            return 0.0;
        }
        $net = (float) $row['debit'] - (float) $row['credit'];
        // Income types are credit-natured; present as positive income.
        return in_array($type, ChartOfAccounts::DEBIT_TYPES, true) ? round($net, 2) : round(-$net, 2);
    }

    /** @return array<string,float> headline numbers for the finance dashboard. */
    public static function dashboard(): array
    {
        $fyStart = self::financialYearStart();
        $monthStart = date('Y-m-01');
        $today = date('Y-m-d');

        $incomeMtd = self::typeTotal('income', $monthStart, $today) + self::typeTotal('other_income', $monthStart, $today);
        $expenseMtd = self::typeTotal('expense', $monthStart, $today)
            + self::typeTotal('cost_of_sales', $monthStart, $today)
            + self::typeTotal('other_expense', $monthStart, $today);
        $incomeFy = self::typeTotal('income', $fyStart, $today) + self::typeTotal('other_income', $fyStart, $today);
        $expenseFy = self::typeTotal('expense', $fyStart, $today)
            + self::typeTotal('cost_of_sales', $fyStart, $today)
            + self::typeTotal('other_expense', $fyStart, $today);

        return [
            'bank_balance' => self::accountBalance('1000'),
            'accounts_receivable' => self::accountBalance('1100'),
            'accounts_payable' => self::accountBalance('2000'),
            'provider_funds_held' => self::accountBalance('2400'),
            'income_mtd' => $incomeMtd,
            'expense_mtd' => $expenseMtd,
            'net_mtd' => round($incomeMtd - $expenseMtd, 2),
            'income_fy' => $incomeFy,
            'expense_fy' => $expenseFy,
            'net_fy' => round($incomeFy - $expenseFy, 2),
            'posted_entries' => (int) Database::scalar("SELECT COUNT(*) FROM owner_finance_journal_entries WHERE status = 'posted'"),
            'draft_entries' => (int) Database::scalar("SELECT COUNT(*) FROM owner_finance_journal_entries WHERE status = 'draft'"),
        ];
    }

    private static function financialYearStart(): string
    {
        $m = (int) date('n');
        $y = (int) date('Y');
        $startYear = $m >= 7 ? $y : $y - 1;
        return sprintf('%04d-07-01', $startYear);
    }
}
