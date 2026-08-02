<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Admin\AdminApiHtmlBridge;
use App\Services\Api\AdminApiServiceAccountService;
use App\Services\AuditLog;

/**
 * Browser admin for Admin API service accounts (Option B Increment H / OPS-010).
 */
final class ApiServiceAccountsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireManager();

        try {
            $accounts = AdminApiHtmlBridge::run(static fn (): array => (new AdminApiServiceAccountService())->list());
        } catch (AdminApiException $e) {
            return $this->view('admin.api-service-accounts.index', [
                'title' => 'API service accounts',
                'accounts' => [],
                'unavailable' => $e->getMessage(),
                'createdSecret' => Session::pull('admin_api_created_secret'),
                'rotatedSecret' => Session::pull('admin_api_rotated_secret'),
            ]);
        }

        return $this->view('admin.api-service-accounts.index', [
            'title' => 'API service accounts',
            'accounts' => $accounts,
            'unavailable' => null,
            'createdSecret' => Session::pull('admin_api_created_secret'),
            'rotatedSecret' => Session::pull('admin_api_rotated_secret'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireManager();

        try {
            $result = AdminApiHtmlBridge::run(static fn (): array => (new AdminApiServiceAccountService())->create([
                'name' => $request->input('name'),
                'scopes' => self::parseScopes($request),
                'status' => $request->input('status', 'active'),
            ], $request));
        } catch (AdminApiException $e) {
            return $this->redirectWith('/admin/api-service-accounts', 'error', $e->getMessage());
        }

        if (!empty($result['client_secret'])) {
            Session::set('admin_api_created_secret', (string) $result['client_secret']);
        }
        AuditLog::record('admin_api.service_account_created', 'api_oauth_client', (string) ($result['id'] ?? ''));

        return $this->redirectWith('/admin/api-service-accounts', 'success', 'Service account created. Copy the secret now — it will not be shown again.');
    }

    public function rotate(Request $request): Response
    {
        $this->requireManager();
        $id = trim((string) $request->input('id', ''));

        try {
            $result = AdminApiHtmlBridge::run(static fn (): array => (new AdminApiServiceAccountService())->rotate($id, $request));
        } catch (AdminApiException $e) {
            return $this->redirectWith('/admin/api-service-accounts', 'error', $e->getMessage());
        }

        if (!empty($result['client_secret'])) {
            Session::set('admin_api_rotated_secret', (string) $result['client_secret']);
        }
        AuditLog::record('admin_api.service_account_rotated', 'api_oauth_client', $id);

        return $this->redirectWith('/admin/api-service-accounts', 'success', 'Secret rotated. Copy the new secret now — it will not be shown again.');
    }

    public function disable(Request $request): Response
    {
        $this->requireManager();
        $id = trim((string) $request->input('id', ''));

        try {
            AdminApiHtmlBridge::run(static fn (): array => (new AdminApiServiceAccountService())->update($id, [
                'status' => 'disabled',
            ], $request));
        } catch (AdminApiException $e) {
            return $this->redirectWith('/admin/api-service-accounts', 'error', $e->getMessage());
        }

        AuditLog::record('admin_api.service_account_disabled', 'api_oauth_client', $id);

        return $this->redirectWith('/admin/api-service-accounts', 'success', 'Service account disabled.');
    }

    /** @return list<string> */
    private static function parseScopes(Request $request): array
    {
        $raw = $request->input('scopes');
        if (is_array($raw)) {
            return array_values(array_filter(array_map(static fn ($s): string => trim((string) $s), $raw)));
        }

        $csv = trim((string) $raw);

        return $csv === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    private function requireManager(): void
    {
        if (!auth()->hasAnyRole(...AdminApiServiceAccountService::MANAGER_ROLES)) {
            $this->abort(403, 'Administrator role required to manage API service accounts.');
        }
    }
}
