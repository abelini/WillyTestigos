<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Exception\MethodNotAllowedException;
use Willy\Exception\NotFoundException;
use Willy\Http\Request;
use Willy\Http\Response;
use Willy\Http\Router;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR']);
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    public function testItDispatchesAnExactRoute(): void
    {
        $router = new Router();
        $router->get('/saludo', static fn (Request $request, array $params): Response => Response::html('hola'));

        $response = $router->dispatch($this->request('GET', '/saludo'));

        self::assertSame(200, $response->status);
        self::assertSame('hola', $response->body);
    }

    public function testItExtractsTheIdentifierFromThePath(): void
    {
        $router = new Router();
        $router->get(
            '/api/jobs/{id}',
            static fn (Request $request, array $params): Response => Response::json($params),
        );

        $response = $router->dispatch($this->request('GET', '/api/jobs/' . str_repeat('a', 32)));

        self::assertSame(['id' => str_repeat('a', 32)], $this->decode($response->body));
    }

    public function testItRejectsAnUnknownRoute(): void
    {
        $this->expectException(NotFoundException::class);

        (new Router())->dispatch($this->request('GET', '/no-existe'));
    }

    public function testItRejectsAnUnsupportedMethod(): void
    {
        $router = new Router();
        $router->get('/solo-get', static fn (Request $request, array $params): Response => Response::html('ok'));

        $this->expectException(MethodNotAllowedException::class);

        $router->dispatch($this->request('POST', '/solo-get'));
    }

    private function request(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        return Request::capture();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $body): array
    {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
