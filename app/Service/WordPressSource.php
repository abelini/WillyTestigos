<?php

declare(strict_types=1);

namespace Willy\Service;

use Throwable;
use Willy\Domain\ImageType;
use Willy\Domain\Job;
use Willy\Exception\ProcessingException;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\Json;
use Willy\Support\Logger;
use Willy\Support\Paths;

use function array_key_exists;
use function count;
use function is_int;
use function mb_substr;

/**
 * Llena un trabajo con capturas dibujadas a partir de las entradas de
 * WordPress y deja un manifiesto con el resultado de cada una.
 */
final class WordPressSource
{
    private const MAX_ERROR_LENGTH = 200;

    public function __construct(
        private readonly WpClient $client,
        private readonly WpPostRenderer $renderer,
        private readonly JobFiles $files,
        private readonly Paths $paths,
        private readonly FileSystem $system,
        private readonly Json $json,
        private readonly Logger $logger,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->config->hasWordPress();
    }

    public function prepare(Job $job): void
    {
        $this->writeManifest($job->id, 0, 0, []);
    }

    public function isWordPress(string $jobId): bool
    {
        return $this->system->isFile($this->paths->wordpressManifest($jobId));
    }

    /**
     * @return array{source: string, selected: int, rendered: int}
     */
    public function summary(string $jobId): array
    {
        $empty = ['source' => 'photos', 'selected' => 0, 'rendered' => 0];

        if (!$this->isWordPress($jobId)) {
            return $empty;
        }

        try {
            $data = $this->json->read($this->paths->wordpressManifest($jobId));
        } catch (Throwable $exception) {
            return ['source' => 'wordpress', 'selected' => 0, 'rendered' => 0];
        }

        return [
            'source' => 'wordpress',
            'selected' => $this->int($data, 'selected'),
            'rendered' => $this->int($data, 'rendered'),
        ];
    }

    /**
     * Genera las capturas del mes y devuelve cuántas se pudieron dibujar.
     */
    public function fill(Job $job): int
    {
        $posts = $this->client->postsOfMonth($job->month, $job->coverYear($this->clock));
        $selected = $this->client->selectDaily($posts);

        if ($selected === []) {
            throw new ProcessingException(
                'WordPress no devolvió notas con "' . $this->config->wpSearchTerm
                . '" para ' . $job->month->label() . '.',
            );
        }

        $this->files->prepare($job->id);

        $entries = [];
        $rendered = 0;
        $sequence = 1;

        foreach ($selected as $post) {
            try {
                $image = $this->client->downloadImage($post->imageUrl);
                $this->renderer->render(
                    $post,
                    $image,
                    $this->files->originalPath($job->id, $sequence, ImageType::Jpeg),
                );

                $entries[] = [
                    'id' => $post->id,
                    'date' => $post->day(),
                    'title' => $post->title,
                    'status' => 'ok',
                ];

                $rendered++;
                $sequence++;
            } catch (Throwable $exception) {
                $this->logger->warning('No se pudo generar una captura de WordPress.', [
                    'job' => $job->id,
                    'post' => $post->id,
                    'title' => $post->title,
                    'message' => $exception->getMessage(),
                ]);

                $entries[] = [
                    'id' => $post->id,
                    'date' => $post->day(),
                    'title' => $post->title,
                    'status' => 'error',
                    'message' => mb_substr($exception->getMessage(), 0, self::MAX_ERROR_LENGTH),
                ];
            }
        }

        $this->writeManifest($job->id, count($selected), $rendered, $entries);

        if ($rendered === 0) {
            throw new ProcessingException('No se pudo generar ninguna captura desde WordPress.');
        }

        return $rendered;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function writeManifest(string $jobId, int $selected, int $rendered, array $entries): void
    {
        $this->json->write($this->paths->wordpressManifest($jobId), [
            'source' => 'wordpress',
            'selected' => $selected,
            'rendered' => $rendered,
            'posts' => $entries,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function int(array $data, string $key): int
    {
        if (!array_key_exists($key, $data) || !is_int($data[$key])) {
            return 0;
        }

        return $data[$key];
    }
}
