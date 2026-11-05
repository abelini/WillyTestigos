<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use GdImage;
use PHPUnit\Framework\TestCase;
use Willy\Domain\WpPost;
use Willy\Service\WpPostRenderer;

use function file_get_contents;
use function imagecolorallocate;
use function imagecolorat;
use function imagecreatefromjpeg;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagejpeg;
use function imagesx;
use function imagesy;
use function is_int;
use function is_string;
use function max;
use function min;

final class WpPostRendererTest extends TestCase
{
    public function testItDrawsTheMobileCaptureWithTheFeaturedImage(): void
    {
        TestEnvironment::resetJobs();
        $destination = $this->destination('con-imagen.jpg');

        $this->renderer()->render($this->post(), $this->featured(), $destination);

        $canvas = $this->decode($destination);

        self::assertSame(1170, imagesx($canvas));
        self::assertSame(2532, imagesy($canvas));
        $this->assertRgb($canvas, 6, 6, [186, 59, 48]);
        $this->assertRgb($canvas, 6, 160, [14, 14, 14]);
        $this->assertRgb($canvas, 600, 2530, [255, 255, 255]);
    }

    public function testItDrawsAPlaceholderWhenTheImageIsMissing(): void
    {
        TestEnvironment::resetJobs();
        $destination = $this->destination('sin-imagen.jpg');

        $this->renderer()->render($this->post(), '', $destination);

        $canvas = $this->decode($destination);

        self::assertSame(1170, imagesx($canvas));
        self::assertSame(2532, imagesy($canvas));
        $this->assertRgb($canvas, 6, 6, [186, 59, 48]);
        $this->assertRgb($canvas, 600, 2530, [255, 255, 255]);
    }

    private function renderer(): WpPostRenderer
    {
        return new WpPostRenderer(TestEnvironment::config(), TestEnvironment::files());
    }

    private function destination(string $name): string
    {
        return TestEnvironment::root() . '/render-' . $name;
    }

    private function decode(string $path): GdImage
    {
        $canvas = imagecreatefromjpeg($path);

        if (!$canvas instanceof GdImage) {
            self::fail('No se pudo leer la captura generada.');
        }

        return $canvas;
    }

    private function post(): WpPost
    {
        return new WpPost(
            id: 1,
            title: 'La UAS abre sus puertas',
            link: 'https://example.test/nota',
            imageUrl: 'https://example.test/imagen.jpg',
            content: 'La Universidad Autónoma de Sinaloa informó sobre el proceso de admisión.',
            category: 'Sinaloa',
            publishedAt: new DateTimeImmutable('2026-10-05T09:00:00', TestEnvironment::clock()->zone()),
        );
    }

    private function featured(): string
    {
        $image = imagecreatetruecolor(320, 180);

        if (!$image instanceof GdImage) {
            self::fail('No se pudo crear la imagen destacada de prueba.');
        }

        $color = imagecolorallocate($image, 30, 120, 60);

        if (!is_int($color)) {
            self::fail('No se pudo asignar el color de la imagen destacada.');
        }

        imagefilledrectangle($image, 0, 0, 320, 180, $color);

        $path = TestEnvironment::root() . '/featured.jpg';
        imagejpeg($image, $path, 85);

        $bytes = file_get_contents($path);

        if (!is_string($bytes)) {
            self::fail('No se pudo leer la imagen destacada de prueba.');
        }

        return $bytes;
    }

    /**
     * @param list<int> $expected
     */
    private function assertRgb(GdImage $canvas, int $x, int $y, array $expected): void
    {
        $color = imagecolorat($canvas, $x, $y);

        if (!is_int($color)) {
            self::fail('No se pudo leer el color de la captura.');
        }

        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        self::assertLessThanOrEqual(18, max(
            $this->difference($red, $expected[0]),
            $this->difference($green, $expected[1]),
            $this->difference($blue, $expected[2]),
        ));
    }

    private function difference(int $actual, int $expected): int
    {
        return max($actual, $expected) - min($actual, $expected);
    }
}
