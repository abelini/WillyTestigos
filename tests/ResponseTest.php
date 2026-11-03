<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Http\Response;

final class ResponseTest extends TestCase
{
    public function testJsonResponseEncodesThePayload(): void
    {
        $response = Response::json(['ok' => true, 'total' => 9], 201);
        $payload = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        self::assertTrue(is_array($payload) && $payload['ok'] === true);
    }

    public function testDownloadResponseUsesRfc5987FileNames(): void
    {
        $response = Response::download('%PDF-1.4', 'TESTIGOS DEL MES DE OCTUBRE.pdf');

        self::assertSame(200, $response->status);
        self::assertSame('application/pdf', $response->headers['Content-Type']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertStringContainsString('filename="TESTIGOS DEL MES DE OCTUBRE.pdf"', $response->headers['Content-Disposition']);
        self::assertStringContainsString(
            "filename*=UTF-8''TESTIGOS%20DEL%20MES%20DE%20OCTUBRE.pdf",
            $response->headers['Content-Disposition'],
        );
    }

    public function testHeadersAreMerged(): void
    {
        $response = Response::html('hola')
            ->withHeader('X-Test', 'uno')
            ->withHeaders(['X-Test' => 'dos', 'X-Otro' => 'tres']);

        self::assertSame('dos', $response->headers['X-Test']);
        self::assertSame('tres', $response->headers['X-Otro']);
    }

    public function testTheAfterCallbackRuns(): void
    {
        $executed = false;
        $response = Response::html('ok')->withAfter(static function () use (&$executed): void {
            $executed = true;
        });

        $after = $response->after;
        $after();

        self::assertTrue($executed);
    }
}
