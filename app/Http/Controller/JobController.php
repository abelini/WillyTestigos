<?php

declare(strict_types=1);

namespace Willy\Http\Controller;

use Throwable;
use Willy\Domain\DispatchMode;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Domain\Month;
use Willy\Exception\NotFoundException;
use Willy\Exception\StateException;
use Willy\Http\Request;
use Willy\Http\Response;
use Willy\Repository\JobStore;
use Willy\Service\Auth;
use Willy\Service\DeliveryService;
use Willy\Service\JobDispatcher;
use Willy\Service\JobProcessor;
use Willy\Service\UploadService;
use Willy\Service\WordPressSource;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\Csrf;
use Willy\Support\FileSystem;
use Willy\Support\Logger;
use Willy\Support\Process;

use function array_key_exists;

final class JobController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly JobStore $jobs,
        private readonly UploadService $uploads,
        private readonly JobDispatcher $dispatcher,
        private readonly JobProcessor $processor,
        private readonly DeliveryService $delivery,
        private readonly FileSystem $files,
        private readonly Clock $clock,
        private readonly Process $process,
        private readonly Logger $logger,
        private readonly WordPressSource $wordpress,
        private readonly Config $config,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function upload(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $this->csrf->verify($request->csrfToken());

        $job = $this->uploads->accept(
            $this->jobId($params),
            $request->file('photo'),
            $request->postInt('position', 0),
        );

        return Response::json([
            'ok' => true,
            'job' => $job->toView($this->clock),
        ], 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function start(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $this->csrf->verify($request->csrfToken());

        return $this->dispatchJob($this->uploads->finish($this->jobId($params)));
    }

    /**
     * Crea un trabajo cuyas capturas se dibujan desde las entradas de
     * WordPress del mes elegido.
     *
     * @param array<string, string> $params
     */
    public function wordpress(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $this->csrf->verify($request->csrfToken());

        if (!$this->wordpress->isConfigured()) {
            throw new StateException('WordPress no está configurado: define WP_BASE_URL en el archivo .env.');
        }

        $month = Month::fromNumber(
            $request->postInt('month', Month::detect($this->clock->now())->number()),
        );

        $job = $this->jobs->create($month, $this->config->coverSubtitle);
        $this->wordpress->prepare($job);
        $now = $this->clock->nowTimestamp();
        $queued = $this->jobs->update(
            $job->id,
            static fn (Job $current): Job => $current->withStatus(JobStatus::Queued, $now),
        );

        return $this->dispatchJob($queued);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $job = $this->jobs->find($this->jobId($params));

        return Response::json([
            'ok' => true,
            'job' => $job->toView($this->clock),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function download(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $job = $this->jobs->find($this->jobId($params));
        $now = $this->clock->nowTimestamp();

        if (!$job->hasPdf()) {
            throw new NotFoundException('El PDF todavía no está disponible.');
        }

        if ($job->isExpired($now)) {
            $this->jobs->delete($job->id);

            throw new NotFoundException('El PDF expiró y fue eliminado.');
        }

        if (!$this->files->isFile($job->pdfPath)) {
            throw new NotFoundException('El PDF ya no está disponible.');
        }

        return Response::download($this->files->read($job->pdfPath), $job->fileName());
    }

    /**
     * @param array<string, string> $params
     */
    public function retry(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $this->csrf->verify($request->csrfToken());

        $job = $this->jobs->find($this->jobId($params));
        $now = $this->clock->nowTimestamp();

        if (!$job->hasPdf()) {
            throw new NotFoundException('El PDF todavía no está disponible.');
        }

        if ($job->isExpired($now)) {
            throw new NotFoundException('El PDF expiró y ya no se puede reenviar.');
        }

        $updated = $this->delivery->deliver($job->id);

        return Response::json([
            'ok' => true,
            'job' => $updated->toView($this->clock),
        ]);
    }

    /**
     * Despacha el trabajo y, si se procesa en línea, lo ejecuta después de
     * cerrar la respuesta.
     */
    private function dispatchJob(Job $job): Response
    {
        $mode = $this->dispatcher->dispatch($job);

        $response = Response::json([
            'ok' => true,
            'mode' => $mode->value,
            'job' => $this->jobs->find($job->id)->toView($this->clock),
        ], 202);

        if ($mode !== DispatchMode::Inline) {
            return $response;
        }

        $jobId = $job->id;

        return $response->withAfter(function () use ($jobId): void {
            $this->process->detach();
            $this->process->finishRequest();

            try {
                $this->processor->process($jobId);
            } catch (Throwable $exception) {
                $this->logger->error('Falló el procesamiento en línea del trabajo.', [
                    'job' => $jobId,
                    'tipo' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * @param array<string, string> $params
     */
    private function jobId(array $params): string
    {
        if (!array_key_exists('id', $params)) {
            throw new NotFoundException('El trabajo solicitado no existe.');
        }

        return $params['id'];
    }
}
