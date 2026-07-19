<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;

/**
 * In-dashboard viewer for application logs (storage/logs/*.log with a database
 * fallback when the log directory is not writable on shared hosting).
 */
final class LogsController extends Controller
{
    private const MIN_LINES = 50;
    private const MAX_LINES = 2000;

    public function index(Request $request): Response
    {
        $this->requireSuperAdmin();

        $channel = $this->sanitiseChannel((string) $request->input('channel', ''));
        $channels = Logger::channels();
        if ($channel === '' || !in_array($channel, $channels, true)) {
            $channel = $channels[0] ?? 'email';
        }

        $lines = (int) $request->input('lines', 300);
        $lines = max(self::MIN_LINES, min(self::MAX_LINES, $lines));

        $entries = Logger::tail($channel, $lines);
        $content = implode("\n", array_map(static fn (array $e): string => $e['line'], $entries));

        $path = base_path('storage/logs/' . $channel . '.log');
        $size = is_file($path) ? (int) filesize($path) : 0;
        $modified = is_file($path) ? (filemtime($path) ?: null) : null;
        $diag = Logger::diagnostics();
        $fromDb = count(array_filter($entries, static fn (array $e): bool => ($e['source'] ?? '') === 'database'));

        return $this->view('admin.logs.index', [
            'title'    => 'System logs',
            'channels' => $channels,
            'channel'  => $channel,
            'lines'    => $lines,
            'content'  => $content,
            'size'     => $size,
            'modified' => $modified,
            'exists'   => is_file($path) || $fromDb > 0,
            'diag'     => $diag,
            'fromDb'   => $fromDb,
        ]);
    }

    public function repair(Request $request): Response
    {
        $this->requireSuperAdmin();

        $diag = Logger::repair();
        AuditLog::record('system.logs.repair', 'log', null, null, json_encode($diag) ?: null);

        if ($diag['writable'] || $diag['db_table']) {
            return $this->redirectWith('/admin/logs', 'success', 'Log storage checked. '
                . ($diag['writable'] ? 'File logging is writable.' : 'File logging not writable — using database fallback.')
                . ($diag['db_table'] ? ' Database table ready.' : ' Run Maintenance → Apply database updates to enable the database log fallback.'));
        }

        return $this->redirectWith('/admin/logs', 'error', 'Could not write logs. Path: ' . $diag['path']
            . '. Set folder permissions to 755 or 775 in cPanel File Manager (storage/logs), then try again.');
    }

    public function test(Request $request): Response
    {
        $this->requireSuperAdmin();

        $channel = $this->sanitiseChannel((string) $request->input('channel', 'app')) ?: 'app';
        Logger::info('Test log entry from admin (channel: ' . $channel . ').', [
            'user_id' => Auth::instance()->id(),
            'time'    => date('c'),
        ], $channel);

        $diag = Logger::diagnostics();
        AuditLog::record('system.logs.test', 'log', $channel);

        return $this->redirectWith(
            '/admin/logs?channel=' . urlencode($channel),
            'success',
            'Test entry written to the "' . $channel . '" channel. '
            . ($diag['writable'] ? 'File logging OK.' : 'Saved via database fallback (file path not writable).')
        );
    }

    public function clear(Request $request): Response
    {
        $this->requireSuperAdmin();

        $channel = $this->sanitiseChannel((string) $request->input('channel', ''));
        if ($channel === '') {
            return $this->redirectWith('/admin/logs', 'error', 'Unknown log channel.');
        }

        $path = base_path('storage/logs/' . $channel . '.log');
        if (is_file($path)) {
            @file_put_contents($path, '');
        }

        try {
            if (\App\Core\Database::tableExists('system_logs')) {
                \App\Core\Database::query('DELETE FROM system_logs WHERE channel = ?', [$channel]);
            }
        } catch (\Throwable) {
            // non-fatal
        }

        AuditLog::record('system.logs.clear', 'log', $channel);
        return $this->redirectWith('/admin/logs?channel=' . urlencode($channel), 'success', 'Cleared the "' . $channel . '" log.');
    }

    private function sanitiseChannel(string $channel): string
    {
        return (string) preg_replace('/[^a-z0-9_\-]/i', '', $channel);
    }

    private function requireSuperAdmin(): void
    {
        if (!Auth::instance()->isSuperAdmin()) {
            $this->abort(403, 'Logs are restricted to super administrators.');
        }
    }
}
