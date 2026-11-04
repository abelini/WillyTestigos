<?php

declare(strict_types=1);

namespace Willy\Http;

use Random\Randomizer;
use Throwable;
use Willy\Container;
use Willy\Exception\AppException;
use Willy\Http\Controller\AuthController;
use Willy\Http\Controller\DashboardController;
use Willy\Http\Controller\JobController;
use Willy\Service\Auth;
use Willy\Support\Csrf;
use Willy\Support\Session;
use Willy\Support\View;

use function str_starts_with;

final class Application
{
    private readonly ResponseEmitter $emitter;

    public function __construct(private readonly string $root)
    {
        $this->emitter = new ResponseEmitter();
    }

    public function run(): void
    {
        $request = Request::capture();

        try {
            $container = Container::create($this->root);
        } catch (Throwable $exception) {
            $this->emitter->emit($this->fatal($exception));

            return;
        }

        try {
            $session = Session::start($container->config, $container->paths, $container->clock);

            if ($container->config->trustProxy) {
                // Detrás de Cloudflare proxied el REMOTE_ADDR es de Cloudflare;
                // la IP real del cliente llega en CF-Connecting-IP.
                $request = $request->withClientIp();
            }

            $auth = new Auth(
                $session,
                $container->hasher,
                $container->limiter,
                $container->config,
            );
            $csrf = new Csrf($session, new Randomizer());
            $container->cleanup->maybeRun();

            $router = $this->router($container, $auth, $csrf);
            $response = $router->dispatch($request);
        } catch (Throwable $exception) {
            $container->logger->error('Falló una solicitud HTTP.', [
                'path' => $request->path,
                'message' => $exception->getMessage(),
            ]);
            $response = $this->errorResponse($exception, $request);
        }

        $this->emitter->emit($response->withHeaders($this->securityHeaders($container)));
    }

    private function router(Container $container, Auth $auth, Csrf $csrf): Router
    {
        $router = new Router();
        $authController = new AuthController($auth, $csrf);
        $dashboard = new DashboardController(
            $auth,
            $csrf,
            $container->jobs,
            $container->cleanup,
            $container->config,
            $container->clock,
        );
        $jobs = new JobController(
            $auth,
            $csrf,
            $container->jobs,
            $container->uploads,
            $container->dispatcher,
            $container->processor,
            $container->delivery,
            $container->files,
            $container->clock,
            $container->process,
            $container->logger,
        );

        $router->get('/login', [$authController, 'showLogin']);
        $router->post('/login', [$authController, 'login']);
        $router->post('/logout', [$authController, 'logout']);

        $router->get('/', [$dashboard, 'index']);
        $router->get('/api/state', [$dashboard, 'state']);
        $router->post('/api/jobs', [$dashboard, 'create']);

        $router->post('/api/jobs/{id}/photos', [$jobs, 'upload']);
        $router->post('/api/jobs/{id}/start', [$jobs, 'start']);
        $router->post('/api/jobs/{id}/retry', [$jobs, 'retry']);
        $router->get('/api/jobs/{id}', [$jobs, 'show']);
        $router->get('/api/jobs/{id}/download', [$jobs, 'download']);

        return $router;
    }

    private function errorResponse(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof AppException) {
            $status = $exception->httpStatus();
            $code = $exception->errorCode();
            $message = $exception->getMessage();
        } else {
            $status = 500;
            $code = 'internal_error';
            $message = 'Ocurrió un error inesperado.';
        }

        if (str_starts_with($request->path, '/api/')) {
            return Response::json([
                'ok' => false,
                'error' => $code,
                'message' => $message,
            ], $status);
        }

        return Response::html(View::render('layout', [
            'title' => 'Error',
            'content' => View::render('error', [
                'code' => $code,
                'message' => $message,
            ]),
            'config' => View::json(['authenticated' => false]),
        ]), $status);
    }

    private function fatal(Throwable $exception): Response
    {
        return Response::html(
            '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Error</title></head>'
            . '<body><h1>Error de configuración</h1>'
            . '<p>No se pudo iniciar la aplicación. Revisa el archivo .env y los permisos de storage.</p>'
            . '</body></html>',
            500,
        );
    }

    /**
     * @return array<string, string>
     */
    private function securityHeaders(Container $container): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; base-uri 'none'; frame-ancestors 'none'; "
                . "form-action 'self'; img-src 'self' data:; "
                . "style-src 'self' 'unsafe-inline' https://ka-f.fontawesome.com; "
                . "script-src 'self' https://kit.fontawesome.com; "
                . "font-src 'self' https://ka-f.fontawesome.com; "
                . "connect-src 'self' https://ka-f.fontawesome.com",
        ];

        if ($container->config->sessionSecure) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}
