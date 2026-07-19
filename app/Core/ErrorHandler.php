<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Middleware\SecurityHeaders;
use Throwable;

/**
 * Registers global error/exception handlers. In debug mode it shows detail;
 * in production it logs and renders friendly error pages without stack traces.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::renderFatal($error);
            }
        });
    }

    public static function handleException(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ], 'errors');
        }

        $debug = (bool) config('app.debug', false);
        ob_start();
        if ($debug && $status >= 500) {
            self::renderDebug($e);
        } else {
            self::renderErrorPage(
                $status,
                $status >= 500 ? 'A server error occurred.' : $e->getMessage()
            );
        }
        $content = (string) ob_get_clean();

        try {
            $request = Request::capture();
            $response = (new SecurityHeaders())->handle(
                $request,
                static fn (): Response => Response::html($content, $status)
            );
            $response->send();
        } catch (Throwable) {
            if (!headers_sent()) {
                http_response_code($status);
                header('Content-Type: text/html; charset=UTF-8');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: no-store');
            }
            echo $content;
        }
    }

    private static function renderErrorPage(int $status, string $message): void
    {
        $map = [
            403 => 'errors.403',
            404 => 'errors.404',
            405 => 'errors.404',
            503 => 'errors.maintenance',
        ];
        $template = $map[$status] ?? 'errors.500';

        try {
            echo View::render($template, [
                'status'  => $status,
                'message' => $message,
            ]);
        } catch (Throwable) {
            echo "<h1>{$status}</h1><p>" . e($message ?: 'An error occurred.') . '</p>';
        }
    }

    private static function renderDebug(Throwable $e): void
    {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Application Error</title>';
        echo '<style>body{font-family:monospace;background:#1e1e1e;color:#eee;padding:2rem;line-height:1.5}'
            . 'h1{color:#ff6b6b}pre{background:#000;padding:1rem;overflow:auto;border-radius:6px}</style></head><body>';
        echo '<h1>' . e(get_class($e)) . '</h1>';
        echo '<p>' . e($e->getMessage()) . '</p>';
        echo '<p><strong>' . e($e->getFile()) . ':' . $e->getLine() . '</strong></p>';
        echo '<pre>' . e($e->getTraceAsString()) . '</pre>';
        echo '</body></html>';
    }

    private static function renderFatal(array $error): void
    {
        Logger::error('Fatal error: ' . $error['message'], $error, 'errors');
        if (!headers_sent()) {
            http_response_code(500);
        }
        if ((bool) config('app.debug', false)) {
            echo '<pre>Fatal: ' . e($error['message']) . ' in ' . e($error['file']) . ':' . $error['line'] . '</pre>';
        } else {
            try {
                echo View::render('errors.500', ['status' => 500, 'message' => 'A server error occurred.']);
            } catch (Throwable) {
                echo '<h1>500</h1><p>A server error occurred.</p>';
            }
        }
    }
}
