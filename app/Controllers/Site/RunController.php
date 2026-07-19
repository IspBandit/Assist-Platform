<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ServiceRun;
use App\Services\EmailQueue;
use App\Services\RunWorkflow;

/**
 * Public service-run listings and the join-run flow. Runs let a provider commit
 * to visiting an area when enough travellers register interest.
 */
final class RunController extends Controller
{
    public function index(Request $request): Response
    {
        $regionId = (int) $request->input('region') ?: null;
        $categoryId = (int) $request->input('category') ?: null;

        return $this->view('public.runs-index', [
            'title'           => 'Service runs forming near you — VanAssist',
            'metaDescription' => 'See upcoming caravan and RV service runs forming across regional Australia and register your interest to help them go ahead.',
            'canonical'       => url('service-runs'),
            'runs'            => ServiceRun::publicListing($regionId, $categoryId),
            'regions'         => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'categories'      => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
            'regionId'        => $regionId,
            'categoryId'      => $categoryId,
        ]);
    }

    public function show(Request $request): Response
    {
        $run = ServiceRun::findPublicBySlug((string) $request->route('slug'));
        if ($run === null) {
            $this->abort(404, 'Service run not found.');
        }
        $id = (int) $run['id'];

        $alreadyJoined = false;
        $myRequests = [];
        $auth = Auth::instance();
        if ($auth->check()) {
            $customer = Database::selectOne('SELECT id FROM customers WHERE user_id = ?', [(int) $auth->id()]);
            if ($customer !== null) {
                $alreadyJoined = (int) Database::scalar(
                    "SELECT COUNT(*) FROM service_run_bookings WHERE run_id = ? AND customer_id = ? AND status <> 'cancelled'",
                    [$id, (int) $customer['id']]
                ) > 0;
                $myRequests = Database::select(
                    "SELECT id, reference, title FROM service_requests WHERE customer_id = ? AND deleted_at IS NULL "
                    . "AND status NOT IN ('completed','closed','cancelled','rejected') ORDER BY created_at DESC",
                    [(int) $customer['id']]
                );
            }
        }

        return $this->view('public.run', [
            'title'           => $run['title'] . ' — VanAssist',
            'metaDescription' => 'Service run ' . $run['title'] . ' with ' . $run['business_name'] . '. Register your interest.',
            'canonical'       => url('service-runs/' . $run['slug']),
            'run'             => $run,
            'towns'           => ServiceRun::towns($id),
            'services'        => ServiceRun::services($id),
            'alreadyJoined'   => $alreadyJoined,
            'myRequests'      => $myRequests,
            'isAuthed'        => $auth->check(),
        ]);
    }

    public function join(Request $request): Response
    {
        $run = ServiceRun::findPublicBySlug((string) $request->route('slug'));
        if ($run === null) {
            $this->abort(404);
        }
        $id = (int) $run['id'];
        $slugUrl = 'service-runs/' . $run['slug'];

        $auth = Auth::instance();
        if (!$auth->check()) {
            Session::set('_intended_url', url($slugUrl));
            return $this->redirectWith('login', 'info', 'Please sign in or create an account to join this run.');
        }

        if (!in_array($run['status'], ServiceRun::PUBLIC_STATUSES, true)) {
            return $this->redirectWith($slugUrl, 'error', 'This run is not currently accepting registrations.');
        }

        $userId = (int) $auth->id();
        $customer = Database::selectOne('SELECT id FROM customers WHERE user_id = ?', [$userId]);
        if ($customer === null) {
            $customerId = Database::insert(
                'INSERT INTO customers (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())',
                [$userId]
            );
        } else {
            $customerId = (int) $customer['id'];
        }

        $existing = (int) Database::scalar(
            "SELECT COUNT(*) FROM service_run_bookings WHERE run_id = ? AND customer_id = ? AND status <> 'cancelled'",
            [$id, $customerId]
        );
        if ($existing > 0) {
            return $this->redirectWith($slugUrl, 'info', 'You have already registered your interest in this run.');
        }

        $requestId = (int) $request->input('request_id') ?: null;
        if ($requestId !== null) {
            $owns = (int) Database::scalar('SELECT COUNT(*) FROM service_requests WHERE id = ? AND customer_id = ?', [$requestId, $customerId]);
            if ($owns === 0) {
                $requestId = null;
            }
        }

        Database::insert(
            'INSERT INTO service_run_bookings (run_id, customer_id, request_id, town_id, status, notes, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$id, $customerId, $requestId, (int) $request->input('town_id') ?: null, 'joined', trim((string) $request->input('notes')) ?: null]
        );

        RunWorkflow::recalcCapacity($id);

        $user = current_user();
        EmailQueue::queueRaw(
            (string) $user['email'],
            (string) $user['name'],
            'You joined ' . (string) $run['title'],
            '<p>Hi ' . e((string) $user['name']) . ',</p><p>You\'ve registered your interest in <strong>' . e((string) $run['title'])
            . '</strong> with ' . e((string) $run['business_name']) . '. We\'ll let you know as the run is confirmed.</p>'
            . '<p><a href="' . e(url($slugUrl)) . '">View the run</a></p>',
            "You joined {$run['title']}. View: " . url($slugUrl)
        );

        return $this->redirectWith($slugUrl, 'success', 'You\'ve registered your interest in this run.');
    }
}
