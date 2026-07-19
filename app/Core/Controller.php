<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Base controller with view rendering and common response helpers.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(View::render($template, $data), $status);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return (new Response('', $status))->withHeader('Location', redirect_location($path));
    }

    protected function back(): Response
    {
        $ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : null;
        return (new Response('', 302))->withHeader('Location', safe_back_url($ref));
    }

    /** Flash a success/error message then redirect. */
    protected function redirectWith(string $path, string $type, string $message): Response
    {
        Session::flash($type, $message);
        return $this->redirect($path);
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    protected function requirePermission(string $permission): void
    {
        if (!can($permission)) {
            $this->abort(403, 'You do not have permission to perform this action.');
        }
    }
}
