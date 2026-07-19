<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Finance\FinanceReport;

/**
 * VanAssist owner-finance dashboard. Reads only POSTED ledger activity so the
 * headline numbers always reconcile to the general ledger.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('owner_finance.view');

        $recent = Database::select(
            'SELECT id, entry_number, transaction_date, description, status, total_debit, source_type '
            . 'FROM owner_finance_journal_entries ORDER BY id DESC LIMIT 10'
        );

        $gstRegistered = (string) (Database::scalar(
            "SELECT setting_value FROM tax_settings WHERE setting_key = 'gst_registered'"
        ) ?: '0') === '1';

        return $this->view('admin.finance.dashboard', [
            'title' => 'Finance dashboard',
            'metrics' => FinanceReport::dashboard(),
            'trial' => FinanceReport::trialBalance(),
            'recent' => $recent,
            'gstRegistered' => $gstRegistered,
        ]);
    }
}
