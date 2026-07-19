<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouterTest extends TestCase
{
    private function request(string $method, string $path): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $path,
        ], []);
    }

    public function testMatchesRouteParameter(): void
    {
        $router = new Router();
        $router->get('/hello/{name}', fn (Request $r) => 'Hi ' . $r->route('name'));

        $response = $router->dispatch($this->request('GET', '/hello/sam'));

        ob_start();
        $response->send();
        $body = (string) ob_get_clean();

        $this->assertSame('Hi sam', $body);
    }

    public function testUnknownRouteThrows404(): void
    {
        $router = new Router();
        $router->get('/', fn () => 'home');

        $this->expectException(HttpException::class);
        $router->dispatch($this->request('GET', '/missing'));
    }

    public function testMethodMismatchThrows405(): void
    {
        $router = new Router();
        $router->get('/only-get', fn () => 'ok');

        try {
            $router->dispatch($this->request('POST', '/only-get'));
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
        }
    }

    public function testNamedRouteUrl(): void
    {
        $router = new Router();
        $router->get('/providers/{slug}', fn () => 'x', 'provider.show');
        $this->assertSame('http://localhost/providers/acme', $router->routeUrl('provider.show', ['slug' => 'acme']));
    }

    public function testMissingMiddlewareFailsClosed(): void
    {
        $router = new Router();
        $router->group(['middleware' => ['missing-security-control']], function (Router $router): void {
            $router->get('/protected', fn () => 'should not run');
        });

        $this->expectException(RuntimeException::class);
        $router->dispatch($this->request('GET', '/protected'));
    }
}
