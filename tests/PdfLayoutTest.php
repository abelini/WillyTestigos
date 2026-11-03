<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Domain\PdfLayout;

final class PdfLayoutTest extends TestCase
{
    public function testCellDimensions(): void
    {
        $layout = new PdfLayout();

        self::assertSame(66.75, $layout->cellWidth());
        self::assertSame(96.0, $layout->cellHeight());
        self::assertSame(8, $layout->perPage());
    }

    public function testPortraitImageFitsInsideTheCell(): void
    {
        $layout = new PdfLayout();
        $placement = $layout->fit(720.0, 1600.0, 0, 0);

        self::assertEqualsWithDelta(43.2, $placement->width, 0.0001);
        self::assertEqualsWithDelta(96.0, $placement->height, 0.0001);
        self::assertEqualsWithDelta(6.0 + ((66.75 - 43.2) / 2), $placement->x, 0.0001);
        self::assertEqualsWithDelta(6.0, $placement->y, 0.0001);
    }

    public function testLandscapeImageFitsInsideTheCell(): void
    {
        $layout = new PdfLayout();
        $placement = $layout->fit(1600.0, 900.0, 0, 0);

        self::assertEqualsWithDelta(66.75, $placement->width, 0.0001);
        self::assertEqualsWithDelta(37.546875, $placement->height, 0.0001);
    }

    public function testPageCountIncludesTheCover(): void
    {
        $layout = new PdfLayout();

        self::assertSame(1, $layout->totalPages(0));
        self::assertSame(2, $layout->totalPages(1));
        self::assertSame(2, $layout->totalPages(8));
        self::assertSame(3, $layout->totalPages(9));
        self::assertSame(13, $layout->totalPages(90));
    }

    public function testEveryPlacementStaysInsideThePage(): void
    {
        $layout = new PdfLayout();

        for ($index = 0; $index < 8; $index++) {
            $placement = $layout->fit(720.0, 1600.0, $layout->columnFor($index), $layout->rowFor($index));

            self::assertGreaterThanOrEqual(0.0, $placement->x);
            self::assertGreaterThanOrEqual(0.0, $placement->y);
            self::assertLessThanOrEqual($layout->pageWidth(), $placement->x + $placement->width);
            self::assertLessThanOrEqual($layout->pageHeight(), $placement->y + $placement->height);
        }
    }
}
