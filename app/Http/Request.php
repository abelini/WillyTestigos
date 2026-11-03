<?php

declare(strict_types=1);

namespace Willy\Http;

use Willy\Exception\ValidationException;

use function array_key_exists;
use function explode;
use function filter_var;
use function is_int;
use function is_string;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class Request
{
    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     * @param array<array-key, mixed> $files
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $files,
        public readonly string $ip,
    ) {
    }

    public static function capture(): self
    {
        $method = self::serverString('REQUEST_METHOD', 'GET');
        $uri = self::serverString('REQUEST_URI', '/');
        $parts = explode('?', $uri, 2);
        $path = self::normalize($parts[0]);

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            post: $_POST,
            files: $_FILES,
            ip: self::serverString('REMOTE_ADDR', '0.0.0.0'),
        );
    }

    /**
     * Devuelve una copia con la IP del cliente real según Cloudflare.
     * Solo debe invocarse cuando la aplicación corre detrás de un proxy
     * de confianza (TRUST_PROXY=true); de lo contrario el atacante
     * podría falsificar la cabecera.
     */
    public function withClientIp(): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            post: $this->post,
            files: $this->files,
            ip: self::proxyClientIp($this->ip),
        );
    }

    private static function proxyClientIp(string $fallback): string
    {
        $header = trim(self::serverString('HTTP_CF_CONNECTING_IP', ''));

        if ($header !== '' && filter_var($header, FILTER_VALIDATE_IP) !== false) {
            return $header;
        }

        return $fallback;
    }

    public function postString(string $key): string
    {
        if (!$this->hasPost($key)) {
            throw new ValidationException("Falta el campo {$key}.");
        }

        $value = $this->post[$key];

        if (!is_string($value) || trim($value) === '') {
            throw new ValidationException("El campo {$key} no es válido.");
        }

        return trim($value);
    }

    public function postStringOrDefault(string $key, string $default): string
    {
        if (!$this->hasPost($key)) {
            return $default;
        }

        $value = $this->post[$key];

        if (!is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? $default : $trimmed;
    }

    public function postInt(string $key, int $default): int
    {
        if (!$this->hasPost($key)) {
            return $default;
        }

        $value = $this->post[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    public function queryInt(string $key, int $default): int
    {
        if (!array_key_exists($key, $this->query)) {
            return $default;
        }

        $value = $this->query[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    public function file(string $key): UploadedFile
    {
        return UploadedFile::fromGlobals($this->files, $key);
    }

    public function csrfToken(): string
    {
        if ($this->hasPost('_csrf')) {
            $value = $this->post['_csrf'];

            if (is_string($value)) {
                return $value;
            }
        }

        $header = self::serverString('HTTP_X_CSRF_TOKEN', '');

        return trim($header);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    private function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    private static function normalize(string $path): string
    {
        $value = trim($path);

        if ($value === '') {
            return '/';
        }

        if (!str_starts_with($value, '/')) {
            $value = '/' . $value;
        }

        if (strlen($value) > 1 && str_ends_with($value, '/')) {
            $value = substr($value, 0, -1);
        }

        return $value;
    }

    private static function serverString(string $key, string $default): string
    {
        if (!array_key_exists($key, $_SERVER)) {
            return $default;
        }

        $value = $_SERVER[$key];

        if (!is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }
}
