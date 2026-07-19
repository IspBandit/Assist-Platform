<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\FoundingGraphicService;
use Throwable;

/**
 * Admin queue for founding free ad graphic requests.
 */
final class PromotionsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('providers.manage');

        if (!FoundingGraphicService::schemaReady()) {
            return $this->view('admin.promotions.index', [
                'title'       => 'Ad graphics',
                'schemaReady' => false,
                'rows'        => [],
                'total'       => 0,
                'page'        => 1,
                'perPage'     => 30,
                'filters'     => [],
                'counts'      => [],
            ]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 30;
        $filters = [
            'status' => trim((string) $request->input('status', '')),
            'q'      => trim((string) $request->input('q', '')),
        ];
        if ($filters['status'] === '' && $request->input('status') === null) {
            $filters['status'] = 'actionable';
        }

        $result = FoundingGraphicService::listForAdmin($filters, $page, $perPage);

        return $this->view('admin.promotions.index', [
            'title'       => 'Ad graphics',
            'schemaReady' => true,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'filters'     => $filters,
            'counts'      => FoundingGraphicService::statusCounts(),
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('providers.manage');

        $promo = FoundingGraphicService::findForAdmin((int) $request->input('id'));
        if ($promo === null) {
            $this->abort(404, 'Promotion request not found.');
        }

        return $this->view('admin.promotions.show', [
            'title'         => 'Ad graphic — ' . $promo['business_name'],
            'promo'         => $promo,
            'imageUrls'     => FoundingGraphicService::imageUrls($promo),
            'logoUrl'       => FoundingGraphicService::imageUrl($promo['logo_path'] ?? null),
            'providerUrl'   => url('admin/providers/show?id=' . (int) $promo['provider_id']),
            'promoSpecs'    => [
                'desktop' => (string) config('promotions.desktop.label'),
                'mobile'  => (string) config('promotions.mobile.label'),
            ],
        ]);
    }

    public function markInProgress(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $promo = FoundingGraphicService::findForAdmin((int) $request->input('id'));
        if ($promo === null) {
            $this->abort(404);
        }

        FoundingGraphicService::markInProgress((int) $promo['provider_id']);
        AuditLog::record('provider.promo_in_progress', 'provider_promotion', (string) $promo['id']);

        return $this->redirectWith(
            '/admin/promotions/show?id=' . (int) $promo['id'],
            'success',
            'Marked in progress.'
        );
    }

    public function deliver(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $promo = FoundingGraphicService::findForAdmin((int) $request->input('id'));
        if ($promo === null) {
            $this->abort(404);
        }

        $desktop = $request->file('graphic_desktop');
        $mobile = $request->file('graphic_mobile');
        if ($desktop === null || ($desktop['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->redirectWith('/admin/promotions/show?id=' . (int) $promo['id'], 'error', 'Please upload the desktop graphic.');
        }
        if ($mobile === null || ($mobile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->redirectWith('/admin/promotions/show?id=' . (int) $promo['id'], 'error', 'Please upload the mobile graphic.');
        }

        try {
            FoundingGraphicService::deliver(
                (int) $promo['provider_id'],
                $desktop,
                $mobile,
                (int) (current_user()['id'] ?? 0) ?: null,
                (bool) $request->input('feature_provider', true)
            );
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/promotions/show?id=' . (int) $promo['id'], 'error', $e->getMessage());
        }

        AuditLog::record('provider.promo_delivered', 'provider_promotion', (string) $promo['id']);

        return $this->redirectWith(
            '/admin/promotions/show?id=' . (int) $promo['id'],
            'success',
            'Ad graphic delivered and provider notified.'
        );
    }
}
