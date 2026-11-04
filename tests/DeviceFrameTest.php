<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Service\DeviceFrame;

use function imagesx;
use function imagesy;
use function intdiv;
use function round;

final class DeviceFrameTest extends TestCase
{
    private const WHITE = ['red' => 255, 'green' => 255, 'blue' => 255];

    public function testItFramesAVerticalCapture(): void
    {
        $screen = ImageFixture::image(400, 800, 30, 120, 220);
        $framed = (new DeviceFrame())->apply($screen);

        self::assertGreaterThan(400, imagesx($framed));
        self::assertGreaterThan(800, imagesy($framed));

        $center = ImageFixture::rgbAt($framed, intdiv(imagesx($framed), 2), intdiv(imagesy($framed), 2));

        self::assertSame(['red' => 30, 'green' => 120, 'blue' => 220], $center);
    }

    public function testItLeavesWhitePaperOutsideTheBody(): void
    {
        $framed = (new DeviceFrame())->apply(ImageFixture::image(400, 800, 30, 120, 220));

        self::assertSame(self::WHITE, ImageFixture::rgbAt($framed, 0, 0));
        self::assertSame(self::WHITE, ImageFixture::rgbAt($framed, imagesx($framed) - 1, 0));
    }

    public function testItDrawsADarkBezelAroundTheScreen(): void
    {
        $framed = (new DeviceFrame())->apply(ImageFixture::image(400, 800, 30, 120, 220));

        $bezel = ImageFixture::rgbAt($framed, 6, intdiv(imagesy($framed), 2));

        self::assertSame(12, $bezel['red']);
        self::assertSame(12, $bezel['green']);
        self::assertSame(16, $bezel['blue']);
    }

    public function testItDrawsThePhysicalButtonsOnlyAtTheirSides(): void
    {
        $framed = (new DeviceFrame())->apply(ImageFixture::image(400, 800, 30, 120, 220));
        $height = imagesy($framed);
        $actionY = (int) round($height * 0.150);
        $powerY = (int) round($height * 0.275);
        $gapY = (int) round($height * 0.500);

        self::assertSame(12, ImageFixture::rgbAt($framed, 1, $actionY)['red']);
        self::assertSame(12, ImageFixture::rgbAt($framed, imagesx($framed) - 2, $powerY)['red']);
        self::assertSame(self::WHITE, ImageFixture::rgbAt($framed, 1, $gapY));
    }

    public function testItRoundsTheInnerCornersOfTheScreen(): void
    {
        $framed = (new DeviceFrame())->apply(ImageFixture::image(400, 800, 30, 120, 220));
        $screenLeft = 13;
        $screenTop = 8;

        self::assertSame(12, ImageFixture::rgbAt($framed, $screenLeft + 1, $screenTop + 1)['red']);

        $center = ImageFixture::rgbAt($framed, $screenLeft + 200, $screenTop + 400);

        self::assertSame(['red' => 30, 'green' => 120, 'blue' => 220], $center);
    }

    public function testItLeavesHorizontalCapturesUntouched(): void
    {
        $screen = ImageFixture::image(800, 400, 30, 120, 220);

        self::assertSame($screen, (new DeviceFrame())->apply($screen));
    }

    public function testItLeavesTooNarrowCapturesUntouched(): void
    {
        $screen = ImageFixture::image(100, 300, 30, 120, 220);

        self::assertSame($screen, (new DeviceFrame())->apply($screen));
    }
}
