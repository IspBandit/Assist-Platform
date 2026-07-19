<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\EmailQueue;

/**
 * Provider prospect CRM: track outreach to potential providers, log contact
 * notes, import/export via CSV and generate registration invitations.
 */
final class ProspectsController extends Controller
{
    private const STATUSES = [
        'not_contacted', 'attempted', 'contacted', 'interested',
        'follow_up', 'invited', 'registered', 'declined', 'do_not_contact',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $where = ['deleted_at IS NULL'];
        $params = [];
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'outreach_status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(business_name LIKE ? OR email LIKE ? OR contact_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*) FROM provider_prospects' . $clause, $params);
        $rows = Database::select(
            'SELECT pp.id, pp.business_name, pp.contact_name, pp.email, pp.phone, pp.outreach_status, '
            . 'pp.next_follow_up_date, t.name AS town_name FROM provider_prospects pp '
            . 'LEFT JOIN towns t ON t.id = pp.base_town_id'
            . $clause . ' ORDER BY pp.updated_at DESC, pp.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return $this->view('admin.prospects.index', [
            'title'     => 'Provider prospects',
            'prospects' => $rows,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'status'    => $status,
            'search'    => $search,
            'statuses'  => self::STATUSES,
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $id = (int) $request->input('id');
        $prospect = $id ? Database::selectOne('SELECT * FROM provider_prospects WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id && $prospect === null) {
            $this->abort(404);
        }

        return $this->view('admin.prospects.form', [
            'title'    => $prospect ? 'Edit prospect' : 'New prospect',
            'prospect' => $prospect,
            'towns'    => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'regions'  => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'statuses' => self::STATUSES,
            'sources'  => ['google', 'facebook', 'referral', 'caravan_park', 'club', 'other'],
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('business_name'));
        if ($name === '') {
            return $this->redirectWith('/admin/prospects', 'error', 'Business name is required.');
        }

        $status = in_array($request->input('outreach_status'), self::STATUSES, true) ? (string) $request->input('outreach_status') : 'not_contacted';
        $source = in_array($request->input('source'), ['google', 'facebook', 'referral', 'caravan_park', 'club', 'other'], true) ? (string) $request->input('source') : 'other';

        $data = [
            'business_name'       => $name,
            'contact_name'        => trim((string) $request->input('contact_name')) ?: null,
            'base_town_id'        => (int) $request->input('base_town_id') ?: null,
            'region_id'           => (int) $request->input('region_id') ?: null,
            'phone'               => trim((string) $request->input('phone')) ?: null,
            'email'               => trim((string) $request->input('email')) ?: null,
            'website'             => trim((string) $request->input('website')) ?: null,
            'services_observed'   => trim((string) $request->input('services_observed')) ?: null,
            'source'              => $source,
            'outreach_status'     => $status,
            'next_follow_up_date' => $request->input('next_follow_up_date') ?: null,
            'notes'               => trim((string) $request->input('notes')) ?: null,
            'updated_at'          => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            $sets = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($data)));
            Database::query("UPDATE provider_prospects SET $sets WHERE id = ?", [...array_values($data), $id]);
            AuditLog::record('prospect.updated', 'provider_prospect', (string) $id, null, $name);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_fill(0, count($data), '?'));
            $id = Database::insert("INSERT INTO provider_prospects ($cols) VALUES ($ph)", array_values($data));
            AuditLog::record('prospect.created', 'provider_prospect', (string) $id, null, $name);
        }

        return $this->redirectWith('/admin/prospects/show?id=' . $id, 'success', 'Prospect saved.');
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $id = (int) $request->input('id');
        $prospect = Database::selectOne(
            'SELECT pp.*, t.name AS town_name FROM provider_prospects pp '
            . 'LEFT JOIN towns t ON t.id = pp.base_town_id WHERE pp.id = ? AND pp.deleted_at IS NULL',
            [$id]
        );
        if ($prospect === null) {
            $this->abort(404);
        }

        return $this->view('admin.prospects.show', [
            'title'       => $prospect['business_name'],
            'prospect'    => $prospect,
            'notes'       => Database::select(
                'SELECT n.*, u.name AS admin_name FROM provider_prospect_notes n '
                . 'LEFT JOIN users u ON u.id = n.admin_id WHERE n.prospect_id = ? ORDER BY n.id DESC',
                [$id]
            ),
            'invitations' => Database::select(
                'SELECT id, email, expires_at, accepted_at, created_at FROM provider_invitations WHERE prospect_id = ? ORDER BY id DESC',
                [$id]
            ),
        ]);
    }

    public function addNote(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $id = (int) $request->input('id');
        $body = trim((string) $request->input('body'));
        $type = in_array($request->input('note_type'), ['call', 'email', 'meeting', 'note'], true) ? (string) $request->input('note_type') : 'note';
        if ($body !== '') {
            Database::query(
                'INSERT INTO provider_prospect_notes (prospect_id, admin_id, note_type, body, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$id, current_user()['id'] ?? null, $type, $body]
            );
            Database::query(
                'UPDATE provider_prospects SET last_contact_date = CURDATE(), updated_at = NOW() WHERE id = ?',
                [$id]
            );
        }
        return $this->redirectWith('/admin/prospects/show?id=' . $id, 'success', 'Note logged.');
    }

    public function invite(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $id = (int) $request->input('id');
        $prospect = Database::selectOne('SELECT * FROM provider_prospects WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($prospect === null) {
            $this->abort(404);
        }
        $email = trim((string) ($request->input('email') ?: $prospect['email']));
        if ($email === '') {
            return $this->redirectWith('/admin/prospects/show?id=' . $id, 'error', 'An email address is required to send an invitation.');
        }

        $token = bin2hex(random_bytes(32));
        $days = 14;
        Database::query(
            'INSERT INTO provider_invitations (prospect_id, email, token_hash, expires_at, created_by, created_at) '
            . 'VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?, NOW())',
            [$id, $email, hash('sha256', $token), $days, current_user()['id'] ?? null]
        );
        Database::query("UPDATE provider_prospects SET outreach_status = 'invited', updated_at = NOW() WHERE id = ?", [$id]);

        $acceptUrl = url('provider/join/' . $token);
        $queued = EmailQueue::queueTemplate('provider_invitation', $email, (string) $prospect['business_name'], [
            'provider_name' => (string) $prospect['business_name'],
            'action_url'    => $acceptUrl,
        ]);
        if (!$queued) {
            EmailQueue::queueRaw(
                $email,
                (string) $prospect['business_name'],
                'You are invited to join VanAssist',
                '<p>Hi ' . e((string) $prospect['business_name']) . ',</p><p>You are invited to create your VanAssist provider profile.</p>'
                . '<p><a href="' . e($acceptUrl) . '">Accept your invitation</a></p>',
                "You are invited to join VanAssist: {$acceptUrl}"
            );
        }

        AuditLog::record('prospect.invited', 'provider_prospect', (string) $id, null, $email);
        return $this->redirectWith('/admin/prospects/show?id=' . $id, 'success', 'Invitation sent to ' . $email . '.');
    }

    public function export(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $rows = Database::select(
            'SELECT business_name, contact_name, email, phone, website, services_observed, source, outreach_status, '
            . 'next_follow_up_date, notes FROM provider_prospects WHERE deleted_at IS NULL ORDER BY business_name'
        );

        $headers = ['business_name', 'contact_name', 'email', 'phone', 'website', 'services_observed', 'source', 'outreach_status', 'next_follow_up_date', 'notes'];
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn ($h) => (string) ($row[$h] ?? ''), $headers));
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        AuditLog::record('prospect.exported', 'provider_prospect', '', null, (string) count($rows));
        return (new Response($csv, 200))
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="provider-prospects-' . date('Ymd') . '.csv"');
    }

    public function import(Request $request): Response
    {
        $this->requirePermission('prospects.manage');
        $file = $request->file('csv');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return $this->redirectWith('/admin/prospects', 'error', 'Please choose a valid CSV file to import.');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            return $this->redirectWith('/admin/prospects', 'error', 'Unable to read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return $this->redirectWith('/admin/prospects', 'error', 'The CSV file appears to be empty.');
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip UTF-8 BOM
        }

        $imported = 0;
        $skipped = 0;
        $cols = count($header);
        while (($line = fgetcsv($handle)) !== false) {
            $line = array_slice(array_pad($line, $cols, null), 0, $cols);
            $record = array_combine($header, $line) ?: [];
            $name = trim((string) ($record['business_name'] ?? ''));
            if ($name === '') {
                $skipped++;
                continue;
            }
            $email = trim((string) ($record['email'] ?? ''));
            if ($email !== '' && (int) Database::scalar('SELECT COUNT(*) FROM provider_prospects WHERE email = ? AND deleted_at IS NULL', [$email]) > 0) {
                $skipped++;
                continue;
            }
            $status = in_array($record['outreach_status'] ?? '', self::STATUSES, true) ? (string) $record['outreach_status'] : 'not_contacted';
            $source = in_array($record['source'] ?? '', ['google', 'facebook', 'referral', 'caravan_park', 'club', 'other'], true) ? (string) $record['source'] : 'other';
            Database::query(
                'INSERT INTO provider_prospects (business_name, contact_name, email, phone, website, services_observed, source, outreach_status, notes, import_date, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW(), NOW())',
                [
                    $name,
                    trim((string) ($record['contact_name'] ?? '')) ?: null,
                    $email ?: null,
                    trim((string) ($record['phone'] ?? '')) ?: null,
                    trim((string) ($record['website'] ?? '')) ?: null,
                    trim((string) ($record['services_observed'] ?? '')) ?: null,
                    $source,
                    $status,
                    trim((string) ($record['notes'] ?? '')) ?: null,
                ]
            );
            $imported++;
        }
        fclose($handle);

        AuditLog::record('prospect.imported', 'provider_prospect', '', null, "imported={$imported};skipped={$skipped}");
        return $this->redirectWith('/admin/prospects', 'success', "Imported {$imported} prospects ({$skipped} skipped).");
    }
}
