<?php

declare(strict_types=1);

namespace Willy\Http;

use Closure;
use Willy\Support\View;

use function rawurlencode;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly Closure $after,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self(
            $status,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            $body,
            self::noop(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            View::json($data),
            self::noop(),
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self(
            $status,
            [
                'Location' => $location,
                'Cache-Control' => 'no-store',
            ],
            '',
            self::noop(),
        );
    }

    public static function download(string $body, string $fileName): self
    {
        $disposition = 'attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName);

        return new self(
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $body,
            self::noop(),
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            $this->status,
            [...$this->headers, ...$headers],
            $this->body,
            $this->after,
        );
    }

    public function withHeader(string $name, string $value): self
    {
        return $this->withHeaders([$name => $value]);
    }

    public function withAfter(Closure $after): self
    {
        return new self($this->status, $this->headers, $this->body, $after);
    }

    private static function noop(): Closure
    {
        return static function (): void {
        };
    }
}
