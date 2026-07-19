<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\Backup;

/**
 * Database backup management — super administrators only. Lists the dumps in
 * storage/backups (outside the web root), generates a new one on demand, and
 * streams or deletes existing files. Filenames are validated to prevent any
 * path traversal.
 */
final class BackupsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireSuperAdmin();

        $files = [];
        foreach ($this->listFiles() as $path) {
            $files[] = [
                'name'     => basename($path),
                'size'     => filesize($path) ?: 0,
                'modified' => date('Y-m-d H:i', filemtime($path) ?: time()),
            ];
        }

        return $this->view('admin.backups.index', [
            'title' => 'Backups',
            'files' => $files,
            'dir'   => 'storage/backups',
        ]);
    }

    public function generate(Request $request): Response
    {
        $this->requireSuperAdmin();
        $result = (new Backup())->run();
        AuditLog::record('backup.generate', 'backup', $result['file'] ?? null, null, $result['method'] ?? null);
        return $this->redirectWith('/admin/backups', 'success', 'Backup created: ' . ($result['file'] ?? 'unknown') . '.');
    }

    public function download(Request $request): Response
    {
        $this->requireSuperAdmin();
        $path = $this->resolve((string) $request->input('file', ''));
        if ($path === null) {
            $this->abort(404, 'Backup not found.');
        }

        AuditLog::record('backup.download', 'backup', basename($path));
        $isGz = str_ends_with($path, '.gz');
        return (new Response((string) file_get_contents($path), 200))
            ->withHeader('Content-Type', $isGz ? 'application/gzip' : 'application/sql')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"');
    }

    public function delete(Request $request): Response
    {
        $this->requireSuperAdmin();
        $path = $this->resolve((string) $request->input('file', ''));
        if ($path === null) {
            $this->abort(404, 'Backup not found.');
        }
        @unlink($path);
        AuditLog::record('backup.delete', 'backup', basename($path));
        return $this->redirectWith('/admin/backups', 'success', 'Backup deleted.');
    }

    private function requireSuperAdmin(): void
    {
        if (!Auth::instance()->isSuperAdmin()) {
            $this->abort(403, 'Backups are restricted to super administrators.');
        }
    }

    /** @return array<int,string> */
    private function listFiles(): array
    {
        $files = glob(base_path('storage/backups') . '/db_*.sql*') ?: [];
        rsort($files);
        return $files;
    }

    /** Validate a requested filename and return its absolute path, or null. */
    private function resolve(string $name): ?string
    {
        $name = basename($name); // strip any path components
        if (!preg_match('/^db_\d{8}_\d{6}\.sql(\.gz)?$/', $name)) {
            return null;
        }
        $path = base_path('storage/backups') . '/' . $name;
        return is_file($path) ? $path : null;
    }
}
