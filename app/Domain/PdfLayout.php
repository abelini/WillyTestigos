<?php

declare(strict_types=1);

namespace Willy\Domain;

use Willy\Exception\ValidationException;

use function intdiv;
use function min;

final class PdfLayout
{
    private const PAGE_WIDTH = 297.0;
    private const PAGE_HEIGHT = 210.0;
    private const MARGIN = 6.0;
    private const GAP = 6.0;
    private const COLUMNS = 4;
    private const ROWS = 2;

    public function pageWidth(): float
    {
        return self::PAGE_WIDTH;
    }

    public function pageHeight(): float
    {
        return self::PAGE_HEIGHT;
    }

    public function margin(): float
    {
        return self::MARGIN;
    }

    public function gap(): float
    {
        return self::GAP;
    }

    public function columns(): int
    {
        return self::COLUMNS;
    }

    public function rows(): int
    {
        return self::ROWS;
    }

    public function perPage(): int
    {
        return self::COLUMNS * self::ROWS;
    }

    public function cellWidth(): float
    {
        return (self::PAGE_WIDTH - (2 * self::MARGIN) - ((self::COLUMNS - 1) * self::GAP)) / self::COLUMNS;
    }

    public function cellHeight(): float
    {
        return (self::PAGE_HEIGHT - (2 * self::MARGIN) - ((self::ROWS - 1) * self::GAP)) / self::ROWS;
    }

    public function originX(int $column): float
    {
        if ($column < 0 || $column >= self::COLUMNS) {
            throw new ValidationException('La columna del PDF no es válida.');
        }

        return self::MARGIN + ($column * ($this->cellWidth() + self::GAP));
    }

    public function originY(int $row): float
    {
        if ($row < 0 || $row >= self::ROWS) {
            throw new ValidationException('La fila del PDF no es válida.');
        }

        return self::MARGIN + ($row * ($this->cellHeight() + self::GAP));
    }

    public function columnFor(int $index): int
    {
        if ($index < 0) {
            throw new ValidationException('El índice de la imagen no es válido.');
        }

        return $index % self::COLUMNS;
    }

    public function rowFor(int $index): int
    {
        if ($index < 0) {
            throw new ValidationException('El índice de la imagen no es válido.');
        }

        return intdiv($index % $this->perPage(), self::COLUMNS);
    }

    public function startsPage(int $index): bool
    {
        if ($index < 0) {
            throw new ValidationException('El índice de la imagen no es válido.');
        }

        return $index % $this->perPage() === 0;
    }

    public function fit(float $imageWidth, float $imageHeight, int $column, int $row): ImagePlacement
    {
        if ($imageWidth <= 0 || $imageHeight <= 0) {
            throw new ValidationException('Las dimensiones de la imagen no son válidas.');
        }

        $scale = min(
            $this->cellWidth() / $imageWidth,
            $this->cellHeight() / $imageHeight,
        );

        $width = $imageWidth * $scale;
        $height = $imageHeight * $scale;
        $x = $this->originX($column) + (($this->cellWidth() - $width) / 2);
        $y = $this->originY($row) + (($this->cellHeight() - $height) / 2);

        return new ImagePlacement($x, $y, $width, $height);
    }

    public function photoPages(int $imageCount): int
    {
        if ($imageCount <= 0) {
            return 0;
        }

        return intdiv($imageCount - 1, $this->perPage()) + 1;
    }

    public function totalPages(int $imageCount): int
    {
        return 1 + $this->photoPages($imageCount);
    }
}
