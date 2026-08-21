<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lightweight server-side PHP template engine with layout inheritance.
 *
 * Templates live in /app/Views and use dot notation (e.g. "public.home").
 * A template may call $this->extend('layouts.public'), define content with
 * $this->section('content') ... $this->endSection(), and the layout outputs
 * sections via $this->yield('content').
 */
final class View
{
    private string $viewPath;
    /** @var array<string,mixed> */
    private array $data = [];
    private ?string $layout = null;
    /** @var array<string,string> */
    private array $sections = [];
    private array $sectionStack = [];

    public function __construct()
    {
        $this->viewPath = base_path('app/Views');
    }

    public static function render(string $template, array $data = []): string
    {
        return (new self())->renderTemplate($template, $data);
    }

    public function renderTemplate(string $template, array $data = []): string
    {
        $this->data = $data;
        $content = $this->renderFile($template, $data);

        // If the template extended a layout, render the layout now.
        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] = $this->sections['content'] ?? $content;
            return $this->renderFile($layout, $data);
        }

        return $content;
    }

    private function renderFile(string $__view, array $__data): string
    {
        // NB: locals are deliberately prefixed ($__view/$__file/$__data) so that
        // a data key such as "template", "file" or "data" cannot collide with the
        // renderer's own variables when extracted into the view's scope.
        $__file = $this->viewPath . '/' . str_replace('.', '/', $__view) . '.php';
        if (!is_file($__file)) {
            throw new RuntimeException("View not found: {$__view} ({$__file})");
        }

        extract($__data, EXTR_SKIP);
        $initialBufferLevel = ob_get_level();
        ob_start();
        try {
            include $__file;
            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            // A template can open nested section buffers before a later include,
            // helper or layout fails. Never leak those buffers into the global
            // error handler or the next response.
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_clean();
            }
            throw $exception;
        }
    }

    // ----- Template helpers (called as $this-> inside views) --------------

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('endSection() called without matching section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function include(string $template, array $data = []): void
    {
        echo $this->renderFile($template, array_merge($this->data, $data));
    }

    public function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
