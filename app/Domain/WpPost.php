<?php

declare(strict_types=1);

namespace Willy\Domain;

use DateTimeImmutable;

use function preg_match;
use function preg_quote;

/**
 * Entrada de WordPress ya normalizada para dibujar su captura móvil.
 */
final class WpPost
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $link,
        public readonly string $imageUrl,
        public readonly string $content,
        public readonly string $category,
        public readonly DateTimeImmutable $publishedAt,
    ) {
    }

    public function day(): string
    {
        return $this->publishedAt->format('Y-m-d');
    }

    /**
     * Fecha con el formato de la portada del sitio: "NOVIEMBRE 5, 2026".
     */
    public function dateLabel(): string
    {
        return $this->monthLabel() . ' ' . $this->publishedAt->format('j') . ', ' . $this->publishedAt->format('Y');
    }

    /**
     * Indica si el término aparece como palabra completa en el título.
     */
    public function mentionsInTitle(string $term): bool
    {
        if ($term === '') {
            return false;
        }

        return preg_match('/\b' . preg_quote($term, '/') . '\b/iu', $this->title) === 1;
    }

    private function monthLabel(): string
    {
        return match ((int) $this->publishedAt->format('n')) {
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
            9 => 'SEPTIEMBRE',
            10 => 'OCTUBRE',
            11 => 'NOVIEMBRE',
            default => 'DICIEMBRE',
        };
    }
}
