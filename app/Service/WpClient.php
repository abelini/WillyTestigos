<?php

declare(strict_types=1);

namespace Willy\Service;

use DateTimeImmutable;
use DateTimeZone;
use Willy\Domain\Month;
use Willy\Domain\WpPost;
use Willy\Exception\ProcessingException;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\HttpClient;
use Willy\Support\Json;

use function array_key_exists;
use function array_merge;
use function array_slice;
use function count;
use function getimagesizefromstring;
use function html_entity_decode;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function ksort;
use function preg_match;
use function preg_replace;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strip_tags;
use function strlen;
use function trim;
use function usort;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Cliente de la API REST de WordPress.
 *
 * Consulta las entradas del mes que mencionan el término configurado y elige
 * las primeras tres de cada día, prefiriendo las que lo llevan en el título.
 */
final class WpClient
{
    private const PER_PAGE = 100;
    private const MAX_PAGES = 12;
    private const MAX_IMAGE_BYTES = 20_971_520;

    public function __construct(
        private readonly HttpClient $http,
        private readonly Json $json,
        private readonly Config $config,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<WpPost>
     */
    public function postsOfMonth(Month $month, int $year): array
    {
        $site = $this->config->wordpressSite();
        $zone = $this->clock->zone();
        $start = new DateTimeImmutable(
            sprintf('%04d-%02d-01T00:00:00', $year, $month->number()),
            $zone,
        );
        $end = $start->modify('+1 month');

        $posts = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = $this->url($site, [
                'search' => $this->config->wpSearchTerm,
                'after' => $start->format('Y-m-d\TH:i:s'),
                'before' => $end->format('Y-m-d\TH:i:s'),
                'orderby' => 'date',
                'order' => 'asc',
                'per_page' => (string) self::PER_PAGE,
                'page' => (string) $page,
                '_embed' => '1',
            ]);

            $response = $this->http->get($url, 45);

            if (!$response->isSuccess()) {
                throw new ProcessingException(
                    'WordPress respondió con el estado HTTP ' . $response->status . '.',
                );
            }

            $rows = $this->rows($this->json->decode($response->body));

            foreach ($rows as $row) {
                if (!$this->isPost($row)) {
                    continue;
                }

                $posts[] = $this->parse($row, $zone);
            }

            if (count($rows) < self::PER_PAGE) {
                break;
            }
        }

        return $posts;
    }

    /**
     * Elige hasta N entradas por día. Las que mencionan el término en el
     * título tienen prioridad; si no alcanzan, se completan con el resto.
     *
     * @param list<WpPost> $posts
     *
     * @return list<WpPost>
     */
    public function selectDaily(array $posts): array
    {
        $term = $this->config->wpSearchTerm;
        $limit = $this->config->wpDailyLimit;

        $days = [];

        foreach ($posts as $post) {
            $days[$post->day()][] = $post;
        }

        ksort($days);

        $selected = [];

        foreach ($days as $dayPosts) {
            usort(
                $dayPosts,
                static fn (WpPost $left, WpPost $right): int => $left->publishedAt->getTimestamp()
                    <=> $right->publishedAt->getTimestamp(),
            );

            $titles = [];
            $others = [];

            foreach ($dayPosts as $post) {
                if ($post->mentionsInTitle($term)) {
                    $titles[] = $post;

                    continue;
                }

                $others[] = $post;
            }

            $chosen = array_slice(array_merge($titles, $others), 0, $limit);
            $selected = array_merge($selected, $chosen);
        }

        return $selected;
    }

    /**
     * Descarga la imagen destacada. Si no está disponible devuelve una cadena
     * vacía para que la captura se dibuje con un marcador gris.
     */
    public function downloadImage(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $response = $this->http->get($url, 30);

        if (!$response->isSuccess()) {
            return '';
        }

        $size = strlen($response->body);

        if ($size === 0 || $size > self::MAX_IMAGE_BYTES) {
            return '';
        }

        if (getimagesizefromstring($response->body) === false) {
            return '';
        }

        return $response->body;
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $site, array $params): string
    {
        $query = [];

        foreach ($params as $key => $value) {
            $query[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return $site . '/wp-json/wp/v2/posts?' . implode('&', $query);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $data): array
    {
        $rows = [];

        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $value;
                }
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isPost(array $row): bool
    {
        $id = $this->field($row, 'id');
        $date = $this->field($row, 'date');
        $link = $this->field($row, 'link');

        return is_int($id)
            && is_string($date)
            && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}$/', $date) === 1
            && is_string($link)
            && $link !== '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function parse(array $row, DateTimeZone $zone): WpPost
    {
        $id = $this->field($row, 'id');
        $date = $this->field($row, 'date');
        $link = $this->field($row, 'link');

        if (!is_int($id) || !is_string($date) || !is_string($link)) {
            throw new ProcessingException('WordPress devolvió una entrada con formato inválido.');
        }

        $publishedAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $date, $zone);

        if (!$publishedAt instanceof DateTimeImmutable) {
            throw new ProcessingException('WordPress devolvió una fecha inválida.');
        }

        $title = $this->plainText($this->rendered($row, 'title'));
        $content = $this->plainText($this->rendered($row, 'content'));

        if ($content === '') {
            $content = $this->plainText($this->rendered($row, 'excerpt'));
        }

        return new WpPost(
            id: $id,
            title: $title === '' ? 'Sin título' : $title,
            link: $link,
            imageUrl: $this->imageUrl($row),
            content: $content,
            category: $this->category($row),
            publishedAt: $publishedAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rendered(array $row, string $key): string
    {
        $value = $this->field($row, $key);

        if (!is_array($value)) {
            return '';
        }

        $rendered = $this->field($value, 'rendered');

        return is_string($rendered) ? $rendered : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function imageUrl(array $row): string
    {
        $embedded = $this->field($row, '_embedded');

        if (is_array($embedded)) {
            $media = $this->field($embedded, 'wp:featuredmedia');

            if (is_array($media) && array_key_exists(0, $media)) {
                $featured = $media[0];

                if (is_array($featured)) {
                    $source = $this->field($featured, 'source_url');

                    if (is_string($source) && $source !== '') {
                        return $source;
                    }
                }
            }
        }

        $jetpack = $this->field($row, 'jetpack_featured_media_url');

        return is_string($jetpack) ? $jetpack : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function category(array $row): string
    {
        $embedded = $this->field($row, '_embedded');

        if (!is_array($embedded)) {
            return '';
        }

        $terms = $this->field($embedded, 'wp:term');

        if (!is_array($terms)) {
            return '';
        }

        foreach ($terms as $group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $term) {
                if (!is_array($term)) {
                    continue;
                }

                $taxonomy = $this->field($term, 'taxonomy');
                $name = $this->field($term, 'name');

                if ($taxonomy === 'category' && is_string($name) && $name !== '') {
                    return $this->plainText($name);
                }
            }
        }

        return '';
    }

    private function plainText(string $html): string
    {
        $blocks = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $html);
        $text = strip_tags(is_string($blocks) ? $blocks : $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $normalized = preg_replace('/\s+/u', ' ', $text);

        return trim(is_string($normalized) ? $normalized : $text);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function field(array $row, string $key): mixed
    {
        return array_key_exists($key, $row) ? $row[$key] : '';
    }
}
