<?php

declare(strict_types=1);

namespace Willy\Support;

use Willy\Exception\AuthException;
use Willy\Exception\StorageException;

use function array_key_exists;
use function ini_get;
use function is_bool;
use function is_dir;
use function is_int;
use function is_string;
use function session_destroy;
use function session_get_cookie_params;
use function session_name;
use function session_regenerate_id;
use function session_save_path;
use function session_set_cookie_params;
use function session_start;
use function session_status;
use function setcookie;

final class Session
{
    private function __construct(
        private readonly Clock $clock,
    ) {
    }

    public static function start(Config $config, Paths $paths, Clock $clock): self
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return new self($clock);
        }

        if (!is_dir($paths->sessions())) {
            throw new StorageException('No existe el directorio de sesiones.');
        }

        session_name($config->sessionName);

        if (session_save_path($paths->sessions()) === false) {
            throw new StorageException('No se pudo configurar el directorio de sesiones.');
        }

        $configured = session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $config->sessionSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!$configured) {
            throw new StorageException('No se pudieron configurar las cookies de sesión.');
        }

        if (!session_start()) {
            throw new StorageException('No se pudo iniciar la sesión.');
        }

        return new self($clock);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function getString(string $key): string
    {
        if (!$this->has($key)) {
            throw new AuthException('La sesión no contiene el dato solicitado.');
        }

        $value = $_SESSION[$key];

        if (!is_string($value)) {
            throw new AuthException('La sesión contiene un dato inválido.');
        }

        return $value;
    }

    public function getInt(string $key): int
    {
        if (!$this->has($key)) {
            throw new AuthException('La sesión no contiene el dato solicitado.');
        }

        $value = $_SESSION[$key];

        if (!is_int($value)) {
            throw new AuthException('La sesión contiene un dato inválido.');
        }

        return $value;
    }

    public function getBool(string $key): bool
    {
        if (!$this->has($key)) {
            throw new AuthException('La sesión no contiene el dato solicitado.');
        }

        $value = $_SESSION[$key];

        if (!is_bool($value)) {
            throw new AuthException('La sesión contiene un dato inválido.');
        }

        return $value;
    }

    public function set(string $key, string|int|bool $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }

    public function regenerate(): void
    {
        if (!session_regenerate_id(true)) {
            throw new StorageException('No se pudo regenerar la sesión.');
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') === '1') {
            $name = session_name();

            if ($name === false) {
                throw new StorageException('No se pudo obtener el nombre de la sesión.');
            }

            $params = session_get_cookie_params();
            setcookie($name, '', [
                'expires' => $this->clock->nowTimestamp() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        if (!session_destroy()) {
            throw new StorageException('No se pudo cerrar la sesión.');
        }
    }
}
