<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Exception\ValidationException;
use Willy\Http\Request;

final class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_X_CSRF_TOKEN'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
        );
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    public function testItNormalizesThePathAndKeepsTheMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/state/?page=2';

        $request = Request::capture();

        self::assertSame('/api/state', $request->path);
        self::assertSame('POST', $request->method);
        self::assertTrue($request->isPost());
        self::assertSame('203.0.113.10', $request->ip);
    }

    public function testPostStringTrimsTheValue(): void
    {
        $_POST = ['mes' => '  noviembre  '];

        self::assertSame('noviembre', Request::capture()->postString('mes'));
    }

    public function testPostStringRejectsAnEmptyValue(): void
    {
        $_POST = ['mes' => '   '];

        $this->expectException(ValidationException::class);

        Request::capture()->postString('mes');
    }

    public function testPostStringRejectsANonStringValue(): void
    {
        $_POST = ['mes' => ['noviembre']];

        $this->expectException(ValidationException::class);

        Request::capture()->postString('mes');
    }

    public function testItReadsIntegersOrDefaults(): void
    {
        $_POST = ['position' => '7', 'invalid' => 'siete'];
        $_GET = ['page' => '3'];

        $request = Request::capture();

        self::assertSame(7, $request->postInt('position', 0));
        self::assertSame(0, $request->postInt('invalid', 0));
        self::assertSame(0, $request->postInt('missing', 0));
        self::assertSame(3, $request->queryInt('page', 1));
        self::assertSame(1, $request->queryInt('missing', 1));
    }

    public function testItReadsTheCsrfTokenFromThePostOrTheHeader(): void
    {
        $_POST = ['_csrf' => 'desde-post'];

        self::assertSame('desde-post', Request::capture()->csrfToken());

        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = '  desde-header  ';

        self::assertSame('desde-header', Request::capture()->csrfToken());
    }

    public function testItReadsAnUploadedFile(): void
    {
        $_FILES = [
            'photo' => [
                'name' => 'captura.jpg',
                'tmp_name' => 'C:/tmp/php123.tmp',
                'type' => 'image/jpeg',
                'error' => UPLOAD_ERR_OK,
                'size' => 4096,
            ],
        ];

        $file = Request::capture()->file('photo');

        self::assertSame('captura.jpg', $file->name);
        self::assertSame('image/jpeg', $file->mime);
        self::assertSame(4096, $file->size);
        self::assertTrue($file->isValid());
    }

    public function testItReportsTheUploadError(): void
    {
        $_FILES = [
            'photo' => [
                'name' => '',
                'tmp_name' => '',
                'type' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ];

        $file = Request::capture()->file('photo');

        self::assertFalse($file->isValid());
        self::assertNotSame('', $file->errorMessage());
    }

    public function testItRejectsAMissingFile(): void
    {
        $this->expectException(ValidationException::class);

        Request::capture()->file('photo');
    }

    public function testItIgnoresTheCloudflareHeaderByDefault(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';

        self::assertSame('203.0.113.10', Request::capture()->ip);
    }

    public function testItResolvesTheCloudflareAddressWhenTrusted(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';

        self::assertSame('198.51.100.7', Request::capture()->withClientIp()->ip);

        $_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8::1';

        self::assertSame('2001:db8::1', Request::capture()->withClientIp()->ip);
    }

    public function testItFallsBackToTheDirectAddressWhenTheProxyHeaderIsInvalid(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'this-is-not-an-ip';

        self::assertSame('203.0.113.10', Request::capture()->withClientIp()->ip);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.99';

        self::assertSame('198.51.100.99', Request::capture()->withClientIp()->ip);
    }
}
