<?php

declare(strict_types=1);

namespace Willy\Support;

use JsonException;
use Willy\Exception\NotFoundException;
use Willy\Exception\ProcessingException;

use function dirname;
use function htmlspecialchars;
use function is_file;
use function json_encode;
use function ob_get_clean;
use function ob_start;
use function preg_match;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data): string
    {
        if (preg_match('/^[a-z0-9_-]+$/', $template) !== 1) {
            throw new NotFoundException('Vista inválida.');
        }

        $path = dirname(__DIR__, 2) . '/resources/views/' . $template . '.php';

        if (!is_file($path)) {
            throw new NotFoundException("No existe la vista {$template}.");
        }

        if (!ob_start()) {
            throw new ProcessingException('No se pudo iniciar el buffer de la vista.');
        }

        require $path;
        $html = ob_get_clean();

        if ($html === false) {
            throw new ProcessingException('No se pudo renderizar la vista.');
        }

        return $html;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_THROW_ON_ERROR
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new ProcessingException('No se pudo serializar la configuración de la vista.');
        }
    }
}
