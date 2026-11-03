<?php

declare(strict_types=1);

namespace Willy\Http;

use Willy\Exception\MethodNotAllowedException;
use Willy\Exception\NotFoundException;

use function array_key_exists;
use function preg_match;
use function str_replace;

final class Router
{
    /**
     * @var list<array{
     *     method: string,
     *     regex: string,
     *     handler: callable(Request, array<string, string>): Response
     * }>
     */
    private array $routes = [];

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = [];

            if (array_key_exists(1, $matches)) {
                $params['id'] = $matches[1];
            }

            $handler = $route['handler'];

            return $handler($request, $params);
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException('Método no permitido.');
        }

        throw new NotFoundException('La ruta solicitada no existe.');
    }

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . str_replace('{id}', '([a-f0-9]{32})', $pattern) . '$#',
            'handler' => $handler,
        ];
    }
}
