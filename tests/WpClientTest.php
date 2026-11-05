<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Willy\Domain\WpPost;
use Willy\Service\WpClient;
use Willy\Support\HttpClient;

use function array_map;

final class WpClientTest extends TestCase
{
    public function testItChoosesThreePostsPerDayPreferringTitleMatches(): void
    {
        $client = $this->client();
        $zone = TestEnvironment::clock()->zone();

        $posts = [
            $this->post(1, '2026-10-01T09:00:00', 'Nota de la mañana', $zone),
            $this->post(2, '2026-10-01T10:00:00', 'La UAS abre convocatoria', $zone),
            $this->post(3, '2026-10-01T11:00:00', 'Otra nota del día', $zone),
            $this->post(4, '2026-10-01T12:00:00', 'Estudiantes de la UAS ganan', $zone),
            $this->post(5, '2026-10-01T13:00:00', 'Cierre del día', $zone),
            $this->post(6, '2026-10-02T08:00:00', 'La UAS en el título', $zone),
        ];

        $selected = $client->selectDaily($posts);

        self::assertSame(
            [2, 4, 1, 6],
            array_map(static fn (WpPost $post): int => $post->id, $selected),
        );
    }

    public function testItNeverExceedsTheDailyLimit(): void
    {
        $client = $this->client();
        $zone = TestEnvironment::clock()->zone();
        $posts = [];

        for ($index = 1; $index <= 6; $index++) {
            $posts[] = $this->post(
                $index,
                '2026-10-03T0' . $index . ':00:00',
                'La UAS informa ' . $index,
                $zone,
            );
        }

        self::assertCount(3, $client->selectDaily($posts));
    }

    public function testItReturnsNothingWithoutPosts(): void
    {
        self::assertSame([], $this->client()->selectDaily([]));
    }

    public function testItMatchesTheTermAsAWholeWord(): void
    {
        $zone = TestEnvironment::clock()->zone();

        self::assertTrue(
            $this->post(1, '2026-10-01T09:00:00', 'La uas abre sus puertas', $zone)->mentionsInTitle('UAS'),
        );
        self::assertFalse(
            $this->post(2, '2026-10-01T09:00:00', 'BUAS y TUAS no cuentan', $zone)->mentionsInTitle('UAS'),
        );
    }

    public function testDateLabelUsesTheSiteFormat(): void
    {
        $post = $this->post(
            1,
            '2026-10-05T09:00:00',
            'La UAS abre sus puertas',
            TestEnvironment::clock()->zone(),
        );

        self::assertSame('OCTUBRE 5, 2026', $post->dateLabel());
    }

    private function client(): WpClient
    {
        return new WpClient(
            new HttpClient(),
            TestEnvironment::json(),
            TestEnvironment::config(),
            TestEnvironment::clock(),
        );
    }

    private function post(int $id, string $date, string $title, DateTimeZone $zone): WpPost
    {
        return new WpPost(
            id: $id,
            title: $title,
            link: 'https://example.test/nota-' . $id,
            imageUrl: '',
            content: 'Contenido de prueba de la nota.',
            category: 'Sinaloa',
            publishedAt: new DateTimeImmutable($date, $zone),
        );
    }
}
