<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\CsvExport;

/**
 * Admin view of customers (caravan/RV owners). A customer record extends a user
 * account, so contact details are read-only here (edit them on the Users screen)
 * and this module focuses on customer-specific fields, saved locations, alerts
 * and the customer's service-request history.
 */
final class CustomersController extends Controller
{
    private const CONTACT = ['email' => 'Email', 'phone' => 'Phone', 'either' => 'Either'];

    public function index(Request $request): Response
    {
        $this->requirePermission('customers.manage');

        $q = trim((string) $request->query('q', ''));
        $townId = (int) $request->query('town', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        [$where, $params] = $this->buildFilter($q, $townId);
        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM customers c JOIN users u ON u.id = c.user_id WHERE ' . $where,
            $params
        );

        $rows = Database::select(
            'SELECT c.id, c.preferred_contact, c.created_at, u.name, u.email, u.phone, t.name AS town_name, '
            . '(SELECT COUNT(*) FROM service_requests sr WHERE sr.customer_id = c.id AND sr.deleted_at IS NULL) AS request_count '
            . 'FROM customers c JOIN users u ON u.id = c.user_id LEFT JOIN towns t ON t.id = c.home_town_id '
            . 'WHERE ' . $where . ' ORDER BY c.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return $this->view('admin.customers.index', [
            'title'   => 'Customers',
            'customers' => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'q'       => $q,
            'townId'  => $townId,
            'towns'   => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('customers.manage');
        $customer = $this->findOr404((int) $request->query('id', 0));
        $id = (int) $customer['id'];

        return $this->view('admin.customers.show', [
            'title'     => (string) $customer['name'],
            'customer'  => $customer,
            'contacts'  => self::CONTACT,
            'towns'     => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'saved'     => Database::select(
                'SELECT csl.label, t.name AS town_name FROM customer_saved_locations csl '
                . 'JOIN towns t ON t.id = csl.town_id WHERE csl.customer_id = ? ORDER BY csl.id DESC',
                [$id]
            ),
            'alerts'    => Database::select(
                'SELECT ca.is_active, t.name AS town_name, rg.name AS region_name, sc.name AS category_name '
                . 'FROM customer_alerts ca '
                . 'LEFT JOIN towns t ON t.id = ca.town_id '
                . 'LEFT JOIN regions rg ON rg.id = ca.region_id '
                . 'LEFT JOIN service_categories sc ON sc.id = ca.category_id '
                . 'WHERE ca.customer_id = ? ORDER BY ca.id DESC',
                [$id]
            ),
            'requests'  => Database::select(
                'SELECT id, reference, title, status, urgency, created_at FROM service_requests '
                . 'WHERE customer_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 50',
                [$id]
            ),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('customers.manage');
        $customer = $this->findOr404((int) $request->input('id', 0));
        $id = (int) $customer['id'];

        $contact = (string) $request->input('preferred_contact', 'either');
        if (!isset(self::CONTACT[$contact])) {
            $contact = 'either';
        }
        $townId = (int) $request->input('home_town_id', 0) ?: null;

        Database::query(
            'UPDATE customers SET preferred_contact = ?, home_town_id = ?, notes = ?, updated_at = NOW() WHERE id = ?',
            [$contact, $townId, trim((string) $request->input('notes', '')) ?: null, $id]
        );
        AuditLog::record('customer.updated', 'customer', (string) $id);
        return $this->redirectWith('/admin/customers/show?id=' . $id, 'success', 'Customer updated.');
    }

    public function export(Request $request): Response
    {
        $this->requirePermission('customers.manage');
        $q = trim((string) $request->query('q', ''));
        $townId = (int) $request->query('town', 0);
        [$where, $params] = $this->buildFilter($q, $townId);

        $rows = Database::select(
            'SELECT c.id, u.name, u.email, u.phone, t.name AS town_name, c.preferred_contact, u.marketing_opt_in, c.created_at, '
            . '(SELECT COUNT(*) FROM service_requests sr WHERE sr.customer_id = c.id AND sr.deleted_at IS NULL) AS request_count '
            . 'FROM customers c JOIN users u ON u.id = c.user_id LEFT JOIN towns t ON t.id = c.home_town_id '
            . 'WHERE ' . $where . ' ORDER BY c.created_at DESC',
            $params
        );

        AuditLog::record('customer.export', 'customer', null, null, (string) count($rows));
        return CsvExport::download(
            'customers-' . date('Ymd-His') . '.csv',
            ['ID', 'Name', 'Email', 'Phone', 'Home town', 'Preferred contact', 'Marketing opt-in', 'Requests', 'Created'],
            array_map(static fn (array $r): array => [
                $r['id'], $r['name'], $r['email'], $r['phone'], $r['town_name'], $r['preferred_contact'],
                $r['marketing_opt_in'] ? 'yes' : 'no', $r['request_count'], $r['created_at'],
            ], $rows)
        );
    }

    // --- helpers ------------------------------------------------------------

    /** @return array{0:string,1:array<int,mixed>} */
    private function buildFilter(string $q, int $townId): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($townId > 0) {
            $where[] = 'c.home_town_id = ?';
            $params[] = $townId;
        }
        return [implode(' AND ', $where), $params];
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $row = $id > 0 ? Database::selectOne(
            'SELECT c.*, u.name, u.email, u.phone, u.status AS user_status, u.marketing_opt_in '
            . 'FROM customers c JOIN users u ON u.id = c.user_id WHERE c.id = ? AND c.deleted_at IS NULL',
            [$id]
        ) : null;
        if ($row === null) {
            $this->abort(404, 'Customer not found.');
        }
        return $row;
    }
}
