<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FinanceAudit;

/**
 * Manage the owner-finance chart of accounts. System (control) accounts are
 * protected from deletion/retyping; accounts with postings cannot be archived.
 */
final class AccountsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('owner_finance.view');
        return $this->view('admin.finance.accounts.index', [
            'title' => 'Chart of accounts',
            'accounts' => ChartOfAccounts::all(),
            'types' => ChartOfAccounts::TYPES,
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('owner_finance.manage_accounts');
        $id = (int) $request->query('id', 0);
        $account = $id > 0 ? ChartOfAccounts::find($id) : null;
        if ($id > 0 && $account === null) {
            $this->abort(404, 'Account not found.');
        }
        return $this->view('admin.finance.accounts.form', [
            'title' => $account ? 'Edit account' : 'New account',
            'account' => $account,
            'types' => ChartOfAccounts::TYPES,
            'taxCodes' => Database::select('SELECT code, name FROM owner_finance_tax_codes WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('owner_finance.manage_accounts');

        $id = (int) $request->input('id', 0);
        $code = trim((string) $request->input('code', ''));
        $name = trim((string) $request->input('name', ''));
        $type = (string) $request->input('type', '');
        $taxCode = trim((string) $request->input('default_tax_code', '')) ?: null;

        if ($name === '' || !isset(ChartOfAccounts::TYPES[$type])) {
            return $this->redirectWith('/admin/finance/accounts', 'error', 'A valid name and account type are required.');
        }

        if ($id > 0) {
            ChartOfAccounts::update($id, $name, $type, $taxCode);
            FinanceAudit::record('account.update', 'owner_finance_account', (string) $id, null, ['name' => $name, 'type' => $type]);
            return $this->redirectWith('/admin/finance/accounts', 'success', 'Account updated.');
        }

        if ($code === '' || !preg_match('/^[A-Za-z0-9.\-]{1,20}$/', $code)) {
            return $this->redirectWith('/admin/finance/accounts', 'error', 'A valid account code (letters, digits, . or -) is required.');
        }
        if (ChartOfAccounts::findByCode($code) !== null) {
            return $this->redirectWith('/admin/finance/accounts', 'error', 'That account code already exists.');
        }

        $newId = ChartOfAccounts::create($code, $name, $type, $taxCode);
        FinanceAudit::record('account.create', 'owner_finance_account', (string) $newId, null, ['code' => $code, 'name' => $name, 'type' => $type]);
        return $this->redirectWith('/admin/finance/accounts', 'success', 'Account created.');
    }

    public function toggle(Request $request): Response
    {
        $this->requirePermission('owner_finance.manage_accounts');
        $id = (int) $request->input('id', 0);
        $account = ChartOfAccounts::find($id);
        if ($account === null) {
            $this->abort(404, 'Account not found.');
        }

        $makeActive = (int) $account['is_active'] === 0;
        if (!$makeActive && (int) $account['is_system'] === 1) {
            return $this->redirectWith('/admin/finance/accounts', 'error', 'System control accounts cannot be archived.');
        }
        if (!$makeActive && ChartOfAccounts::hasPostings($id)) {
            return $this->redirectWith('/admin/finance/accounts', 'error', 'Accounts with posted activity cannot be archived; they hold ledger history.');
        }

        ChartOfAccounts::setActive($id, $makeActive);
        FinanceAudit::record('account.' . ($makeActive ? 'activate' : 'archive'), 'owner_finance_account', (string) $id);
        return $this->redirectWith('/admin/finance/accounts', 'success', $makeActive ? 'Account reactivated.' : 'Account archived.');
    }
}
