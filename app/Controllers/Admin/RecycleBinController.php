<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Admin\AdminApiHtmlBridge;
use App\Services\Api\AdminApiRecycleBinService;
use App\Services\AuditLog;

/**
 * Browser recycle bin for soft-deleted providers and stays (Option B Increment H).
 */
final class RecycleBinController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('providers.manage');

        $entityType = trim((string) $request->input('entity_type', ''));
        $search = trim((string) $request->input('q', ''));

        try {
            $result = AdminApiHtmlBridge::run(function () use ($request, $entityType, $search): array {
                $query = ['limit' => 50];
                if ($entityType !== '') {
                    $query['entity_type'] = $entityType;
                }
                if ($search !== '') {
                    $query['q'] = $search;
                }

                return (new AdminApiRecycleBinService())->list(new Request($query, [], [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/admin/recycle-bin',
                    'REMOTE_ADDR' => $request->ip(),
                ], []));
            });
        } catch (AdminApiException $e) {
            return $this->view('admin.recycle-bin.index', [
                'title' => 'Recycle bin',
                'items' => [],
                'filters' => ['entity_type' => $entityType, 'q' => $search],
                'error' => $e->getMessage(),
            ]);
        }

        return $this->view('admin.recycle-bin.index', [
            'title' => 'Recycle bin',
            'items' => $result['items'] ?? [],
            'filters' => ['entity_type' => $entityType, 'q' => $search],
            'error' => null,
        ]);
    }

    public function restore(Request $request): Response
    {
        $this->requirePermission('providers.manage');

        $entityType = strtolower(trim((string) $request->input('entity_type', '')));
        $id = (int) $request->input('id', 0);

        try {
            AdminApiHtmlBridge::run(static fn (): array => (new AdminApiRecycleBinService())->restore($entityType, $id, $request));
        } catch (AdminApiException $e) {
            return $this->redirectWith('/admin/recycle-bin', 'error', $e->getMessage());
        }

        AuditLog::record('recycle_bin.restored', $entityType, (string) $id);

        return $this->redirectWith('/admin/recycle-bin', 'success', ucfirst($entityType) . ' #' . $id . ' restored.');
    }
}
