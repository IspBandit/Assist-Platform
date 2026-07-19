<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FinanceAudit;
use App\Services\Finance\JournalPostingService;
use Throwable;

/**
 * Journal viewer + manual journal entry. Posted entries are immutable; the only
 * mutation offered here is reversal, which creates an equal-and-opposite entry.
 */
final class JournalsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('owner_finance.view');

        $status = (string) $request->query('status', '');
        $params = [];
        $where = '';
        if (in_array($status, ['draft', 'posted', 'reversed', 'approved'], true)) {
            $where = ' WHERE status = ?';
            $params[] = $status;
        }

        $entries = Database::select(
            'SELECT id, entry_number, transaction_date, posting_date, description, status, '
            . 'total_debit, source_type, source_number FROM owner_finance_journal_entries'
            . $where . ' ORDER BY id DESC LIMIT 200',
            $params
        );

        return $this->view('admin.finance.journals.index', [
            'title' => 'Journals',
            'entries' => $entries,
            'status' => $status,
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('owner_finance.view');
        $id = (int) $request->query('id', 0);
        $entry = Database::selectOne('SELECT * FROM owner_finance_journal_entries WHERE id = ?', [$id]);
        if ($entry === null) {
            $this->abort(404, 'Journal entry not found.');
        }
        $lines = Database::select(
            'SELECT * FROM owner_finance_journal_lines WHERE entry_id = ? ORDER BY line_no',
            [$id]
        );
        $reversal = null;
        if ($entry['reversal_of_entry_id'] !== null) {
            $reversal = Database::selectOne(
                'SELECT id, entry_number FROM owner_finance_journal_entries WHERE id = ?',
                [(int) $entry['reversal_of_entry_id']]
            );
        }
        return $this->view('admin.finance.journals.show', [
            'title' => 'Journal ' . (string) $entry['entry_number'],
            'entry' => $entry,
            'lines' => $lines,
            'reversalOf' => $reversal,
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('owner_finance.manage_journals');
        return $this->view('admin.finance.journals.form', [
            'title' => 'New manual journal',
            'accounts' => ChartOfAccounts::all(false),
            'taxCodes' => Database::select('SELECT code, name FROM owner_finance_tax_codes WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('owner_finance.manage_journals');

        $date = (string) $request->input('transaction_date', date('Y-m-d'));
        $description = trim((string) $request->input('description', ''));
        $accounts = (array) $request->input('account_code', []);
        $debits = (array) $request->input('debit', []);
        $credits = (array) $request->input('credit', []);
        $lineDescs = (array) $request->input('line_description', []);

        $lines = [];
        foreach ($accounts as $i => $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $debit = (float) ($debits[$i] ?? 0);
            $credit = (float) ($credits[$i] ?? 0);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }
            $lines[] = [
                'account_code' => $code,
                'debit' => $debit,
                'credit' => $credit,
                'description' => trim((string) ($lineDescs[$i] ?? '')) ?: null,
            ];
        }

        if (count($lines) < 2) {
            return $this->redirectWith('/admin/finance/journals/new', 'error', 'Enter at least two account lines.');
        }

        try {
            $entryId = JournalPostingService::post([
                'date' => $date,
                'description' => $description !== '' ? $description : 'Manual journal',
                'lines' => $lines,
                'source_type' => 'manual',
                'status' => 'posted',
            ]);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/finance/journals/new', 'error', $e->getMessage());
        }

        FinanceAudit::record('journal.post', 'journal_entry', (string) $entryId, null, ['source' => 'manual']);
        return $this->redirectWith('/admin/finance/journals/show?id=' . $entryId, 'success', 'Journal posted.');
    }

    public function reverse(Request $request): Response
    {
        $this->requirePermission('owner_finance.manage_journals');
        $id = (int) $request->input('id', 0);
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->redirectWith('/admin/finance/journals/show?id=' . $id, 'error', 'A reason is required to reverse an entry.');
        }
        try {
            $reversalId = JournalPostingService::reverse($id, $reason);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/finance/journals/show?id=' . $id, 'error', $e->getMessage());
        }
        return $this->redirectWith('/admin/finance/journals/show?id=' . $reversalId, 'success', 'Entry reversed.');
    }
}
