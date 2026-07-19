<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CsvExport;

/**
 * Read-only viewer for the immutable audit log. Supports filtering by action
 * and free-text, with pagination and CSV export.
 */
final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('audit.view');

        $action = trim((string) $request->input('action', ''));
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        [$where, $params] = $this->filters($action, $search);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM audit_logs a {$where}", $params);
        $rows = Database::select(
            "SELECT a.id, a.action, a.object_type, a.object_id, a.ip_address, a.created_at, u.name AS user_name "
            . "FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id {$where} "
            . "ORDER BY a.id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return $this->view('admin.audit.index', [
            'title'    => 'Audit log',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'action'   => $action,
            'search'   => $search,
            'actions'  => Database::select('SELECT DISTINCT action FROM audit_logs ORDER BY action LIMIT 200'),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->requirePermission('audit.view');
        [$where, $params] = $this->filters(trim((string) $request->input('action', '')), trim((string) $request->input('q', '')));

        $rows = Database::select(
            "SELECT a.created_at, u.name AS user_name, a.action, a.object_type, a.object_id, a.ip_address "
            . "FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id {$where} ORDER BY a.id DESC LIMIT 10000",
            $params
        );

        return CsvExport::download(
            'audit-log-' . date('Ymd') . '.csv',
            ['When', 'User', 'Action', 'Object type', 'Object ID', 'IP'],
            $rows
        );
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function filters(string $action, string $search): array
    {
        $clauses = [];
        $params = [];
        if ($action !== '') {
            $clauses[] = 'a.action = ?';
            $params[] = $action;
        }
        if ($search !== '') {
            $clauses[] = '(a.object_type LIKE ? OR a.object_id LIKE ? OR a.new_value LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
