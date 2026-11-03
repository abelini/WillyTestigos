<?php

declare(strict_types=1);

namespace Willy\Http\Controller;

use Throwable;
use Willy\Domain\DispatchMode;
use Willy\Exception\NotFoundException;
use Willy\Http\Request;
use Willy\Http\Response;
use Willy\Repository\JobStore;
use Willy\Service\Auth;
use Willy\Service\DeliveryService;
use Willy\Service\JobDispatcher;
use Willy\Service\JobProcessor;
use Willy\Service\UploadService;
use Willy\Support\Clock;
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

        $job = $this->uploads->finish($this->jobId($params));
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
