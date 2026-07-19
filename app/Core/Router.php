<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Lightweight HTTP router supporting verb methods, route parameters
 * ({param} and optional {param?}), route groups with shared prefix/middleware,
 * and a middleware pipeline.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:array,handler:mixed,middleware:array,name:?string}> */
    private array $routes = [];

    /** @var array<string,class-string> */
    private array $middlewareAliases = [];

    private array $groupStack = [];

    /** @var array<string,string> name => path pattern */
    private array $namedRoutes = [];

    public function aliasMiddleware(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('PUT', $path, $handler, $name);
    }

    public function patch(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('PATCH', $path, $handler, $name);
    }

    public function delete(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $name);
    }

    public function any(string $path, mixed $handler, ?string $name = null): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $path, $handler, $method === 'GET' ? $name : null);
        }
    }

    private function addRoute(string $method, string $path, mixed $handler, ?string $name): void
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            if (!empty($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        $pattern = $prefix . $path;
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '/' . '') {
            $pattern = '/';
        }

        [$regex, $params] = $this->compile($pattern);

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = $pattern;
        }
    }

    /** @return array{0:string,1:array<int,string>} */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            $optional = ($m[2] ?? '') === '?';
            return $optional ? '(?:/([^/]+))?' : '([^/]+)';
        }, $pattern);

        return ['#^' . $regex . '$#', $params];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['params'] as $i => $paramName) {
                $params[$paramName] = $matches[$i] ?? null;
            }
            $request->setRouteParams($params);

            return $this->runPipeline($request, $route['middleware'], function (Request $req) use ($route) {
                return $this->runHandler($route['handler'], $req);
            });
        }

        if ($allowedMethods !== []) {
            throw new HttpException(405, 'Method Not Allowed');
        }

        throw new HttpException(404, 'Page not found');
    }

    private function runPipeline(Request $request, array $middleware, callable $core): Response
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            function (callable $next, string $name): callable {
                return function (Request $request) use ($next, $name): Response {
                    // Support "alias:arg1,arg2" parameterised middleware.
                    $params = [];
                    if (str_contains($name, ':')) {
                        [$name, $argString] = explode(':', $name, 2);
                        $params = array_map('trim', explode(',', $argString));
                    }
                    $class = $this->middlewareAliases[$name] ?? $name;
                    if (!class_exists($class)) {
                        throw new \RuntimeException(
                            "Configured middleware '{$name}' could not be resolved to class '{$class}'"
                        );
                    }
                    /** @var object $instance */
                    $instance = $params === [] ? new $class() : new $class(...$params);
                    return $this->normalize($instance->handle($request, $next));
                };
            },
            function (Request $request) use ($core): Response {
                return $this->normalize($core($request));
            }
        );

        return $this->normalize($pipeline($request));
    }

    private function runHandler(mixed $handler, Request $request): mixed
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $action] = explode('@', $handler, 2);
            // Fully-qualified names start with "App\"; everything else is
            // resolved relative to the App\Controllers namespace.
            $fqcn = str_starts_with($class, 'App\\') ? $class : 'App\\Controllers\\' . ltrim($class, '\\');
            if (!class_exists($fqcn)) {
                throw new HttpException(500, "Controller not found: {$fqcn}");
            }
            $controller = new $fqcn();
            if (!method_exists($controller, $action)) {
                throw new HttpException(500, "Action not found: {$fqcn}@{$action}");
            }
            return $controller->{$action}($request);
        }

        throw new HttpException(500, 'Invalid route handler.');
    }

    private function normalize(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    public function routeUrl(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }
        $pattern = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $pattern = preg_replace('/\{' . preg_quote($key, '/') . '\??\}/', (string) $value, $pattern);
        }
        return url($pattern);
    }
}
