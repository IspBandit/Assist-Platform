<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaravanPark;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\FileStorage;

/**
 * Admin management of caravan park partners: review applications, approve/reject/
 * suspend, edit details, review documents and triage service-day requests.
 */
final class ParksController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draft', 'pending' => 'Pending', 'active' => 'Active',
        'suspended' => 'Suspended', 'rejected' => 'Rejected',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = CaravanPark::adminListing($status ?: null, $search, $perPage, ($page - 1) * $perPage);

        return $this->view('admin.parks.index', [
            'title'    => 'Caravan parks',
            'parks'    => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
            'search'   => $search,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];

        return $this->view('admin.parks.show', [
            'title'       => $park['name'],
            'park'        => $park,
            'documents'   => CaravanPark::documents($id),
            'serviceDays' => CaravanPark::serviceDayRequests($id),
            'managers'    => Database::select(
                'SELECT u.name, u.email, cpu.role FROM caravan_park_users cpu '
                . 'JOIN users u ON u.id = cpu.user_id WHERE cpu.park_id = ?',
                [$id]
            ),
            'requestCount' => (int) Database::scalar('SELECT COUNT(*) FROM service_requests WHERE park_id = ? AND deleted_at IS NULL', [$id]),
            'claims' => Database::select('SELECT * FROM caravan_park_claims WHERE park_id = ? ORDER BY created_at DESC', [$id]),
            'statuses'    => self::STATUSES,
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        return $this->view('admin.parks.form', [
            'title'   => 'Edit ' . $park['name'],
            'park'    => $park,
            'towns'   => Database::select('SELECT t.id,t.name,s.abbreviation AS state_abbr FROM towns t JOIN states s ON s.id=t.state_id WHERE t.is_active=1 ORDER BY t.name,s.abbreviation'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWith('/admin/parks/form?id=' . $id, 'error', 'Park name is required.');
        }
        $townId = (int) $request->input('town_id') ?: null;
        $town = $townId ? Database::selectOne('SELECT region_id, state_id FROM towns WHERE id = ?', [$townId]) : null;

        Database::query(
            'UPDATE caravan_parks SET name = ?, address = ?, town_id = ?, region_id = ?, state_id = ?, phone = ?, email = ?, '
            . 'website = ?, booking_url = ?, number_of_sites = ?, stay_type = ?, price_type = ?, verification_type = ?, listing_plan = ?, is_featured = ?, public_page_enabled = ?, updated_at = NOW() WHERE id = ?',
            [
                $name,
                trim((string) $request->input('address')) ?: null,
                $townId,
                $town['region_id'] ?? null,
                $town['state_id'] ?? null,
                trim((string) $request->input('phone')) ?: null,
                trim((string) $request->input('email')) ?: null,
                trim((string) $request->input('website')) ?: null,
                trim((string) $request->input('booking_url')) ?: null,
                (int) $request->input('number_of_sites') ?: null,
                in_array($request->input('stay_type'), ['caravan_park','campground','free_camp','showground','rest_area','farm_stay','other'], true) ? $request->input('stay_type') : 'caravan_park',
                in_array($request->input('price_type'), ['free','donation','low_cost','paid','unknown'], true) ? $request->input('price_type') : 'unknown',
                in_array($request->input('verification_type'), ['unverified','community','authority','operator'], true) ? $request->input('verification_type') : 'unverified',
                in_array($request->input('listing_plan'), ['free','verified','premium','featured'], true) ? $request->input('listing_plan') : 'free',
                $request->input('is_featured') ? 1 : 0,
                $request->input('public_page_enabled') ? 1 : 0,
                $id,
            ]
        );
        AuditLog::record('park.updated', 'caravan_park', (string) $id);
        return $this->redirectWith('/admin/parks/show?id=' . $id, 'success', 'Park updated.');
    }

    public function setStatus(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];
        $target = (string) $request->input('status');
        if (!array_key_exists($target, self::STATUSES)) {
            $this->abort(400, 'Unknown status.');
        }

        Database::query('UPDATE caravan_parks SET status = ?, updated_at = NOW() WHERE id = ?', [$target, $id]);
        AuditLog::record('park.status_' . $target, 'caravan_park', (string) $id, (string) $park['status'], $target);

        if ($park['email']) {
            $messages = [
                'active'    => 'Good news — your caravan park is now approved and active on VanAssist.',
                'rejected'  => 'Thank you for your interest. Unfortunately we are unable to approve your park at this time.',
                'suspended' => 'Your caravan park partnership has been suspended. Please contact us for details.',
            ];
            if (isset($messages[$target])) {
                EmailQueue::queueRaw(
                    (string) $park['email'],
                    (string) $park['name'],
                    'VanAssist caravan park update',
                    '<p>Hi ' . e((string) $park['name']) . ',</p><p>' . e($messages[$target]) . '</p>',
                    $messages[$target]
                );
            }
        }

        return $this->redirectWith('/admin/parks/show?id=' . $id, 'success', 'Status updated to ' . self::STATUSES[$target] . '.');
    }

    public function serviceDayStatus(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];
        $sdrId = (int) $request->input('sdr_id');
        $status = (string) $request->input('sdr_status');
        $allowed = ['open', 'reviewing', 'arranged', 'declined', 'completed'];
        if (in_array($status, $allowed, true)) {
            Database::query(
                'UPDATE caravan_park_service_day_requests SET status = ?, updated_at = NOW() WHERE id = ? AND park_id = ?',
                [$status, $sdrId, $id]
            );
        }
        return $this->redirectWith('/admin/parks/show?id=' . $id, 'success', 'Service-day request updated.');
    }

    public function reviewClaim(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $claim = Database::selectOne(
            "SELECT c.*, cp.name AS park_name FROM caravan_park_claims c JOIN caravan_parks cp ON cp.id=c.park_id WHERE c.id=? AND c.status='pending'",
            [(int) $request->input('claim_id')]
        );
        if ($claim === null) { $this->abort(404, 'Pending claim not found.'); }
        $decision = (string) $request->input('decision');
        if (!in_array($decision, ['approved','rejected'], true)) { $this->abort(400, 'Unknown claim decision.'); }
        $user = null;
        if ($decision === 'approved') {
            $user = User::findByEmail((string) $claim['claimant_email']);
            $newUser = $user === null;
            if ($newUser) {
                $userId = User::create([
                    'name'=>(string) $claim['claimant_name'], 'email'=>(string) $claim['claimant_email'],
                    'phone'=>(string) $claim['claimant_phone'], 'password_hash'=>password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'status'=>'active', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s'),
                ]);
                $user = User::find($userId);
            }
            $userId = (int) $user['id'];
            User::assignRoleBySlug($userId, 'caravan-park-partner');
            Database::query('INSERT IGNORE INTO caravan_park_users (park_id,user_id,role,created_at) VALUES (?,?,?,NOW())', [(int) $claim['park_id'],$userId,'owner']);
            Database::query("UPDATE caravan_parks SET verification_type='operator', verified_at=NOW(), updated_at=NOW() WHERE id=?", [(int) $claim['park_id']]);
            if ($newUser) {
                $token = bin2hex(random_bytes(32));
                Database::query('INSERT INTO password_resets (email,token_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR),NOW())', [(string) $claim['claimant_email'],hash('sha256',$token)]);
                EmailQueue::queueTemplate('password_reset',(string) $claim['claimant_email'],(string) $claim['claimant_name'],['customer_name'=>(string) $claim['claimant_name'],'action_url'=>url('reset-password?token='.$token.'&email='.urlencode((string) $claim['claimant_email']))]);
            } else {
                EmailQueue::queueRaw((string) $claim['claimant_email'],(string) $claim['claimant_name'],'Your VanAssist stay listing claim was approved','<p>Your claim for <strong>'.e((string) $claim['park_name']).'</strong> was approved. Sign in to manage it from your Park Partner dashboard.</p>','Your VanAssist stay listing claim was approved. Sign in to manage the listing.');
            }
        } else {
            EmailQueue::queueRaw((string) $claim['claimant_email'],(string) $claim['claimant_name'],'Update on your VanAssist listing claim','<p>We could not approve your claim for <strong>'.e((string) $claim['park_name']).'</strong> using the supplied evidence. Reply to support with further proof of authority.</p>','We could not approve the listing claim using the supplied evidence.');
        }
        Database::query('UPDATE caravan_park_claims SET status=?, reviewed_by=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?', [$decision,(int) current_user()['id'],(int) $claim['id']]);
        AuditLog::record('park.claim_'.$decision,'caravan_park',(string) $claim['park_id'],null,(string) $claim['claimant_email']);
        return $this->redirectWith('/admin/parks/show?id='.(int) $claim['park_id'],'success','Claim '.$decision.'.');
    }

    public function downloadDocument(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $doc = Database::selectOne('SELECT * FROM caravan_park_documents WHERE id = ?', [(int) $request->input('document_id')]);
        if ($doc === null) {
            $this->abort(404);
        }
        return FileStorage::serve('park_documents', (string) $doc['stored_name'], (string) ($doc['original_name'] ?? 'document'), (string) $doc['mime_type'], false);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $park = CaravanPark::adminFind($id);
        if ($park === null) {
            $this->abort(404, 'Park not found.');
        }
        return $park;
    }
}
