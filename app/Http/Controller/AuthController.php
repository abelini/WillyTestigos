<?php

declare(strict_types=1);

namespace Willy\Http\Controller;

use Willy\Exception\AuthException;
use Willy\Http\Request;
use Willy\Http\Response;
use Willy\Service\Auth;
use Willy\Support\Csrf;
use Willy\Support\View;

final class AuthController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function showLogin(Request $request, array $params): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/');
        }

        return $this->render('', 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function login(Request $request, array $params): Response
    {
        $this->csrf->verify($request->csrfToken());

        try {
            $this->auth->attempt($request->postString('password'), $request->ip);
        } catch (AuthException $exception) {
            return $this->render($exception->getMessage(), $exception->httpStatus());
        }

        return Response::redirect('/');
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(Request $request, array $params): Response
    {
        $this->csrf->verify($request->csrfToken());
        $this->auth->logout();

        return Response::redirect('/login');
    }

    private function render(string $error, int $status): Response
    {
        $content = View::render('login', [
            'csrf' => $this->csrf->token(),
            'error' => $error,
        ]);

        return Response::html(View::render('layout', [
            'title' => 'Acceso',
            'content' => $content,
            'config' => View::json(['authenticated' => false]),
        ]), $status);
    }
}
