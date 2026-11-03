<?php

declare(strict_types=1);

namespace Willy;

use Random\Randomizer;
use Willy\Domain\PdfLayout;
use Willy\Repository\JobStore;
use Willy\Service\CleanupService;
use Willy\Service\DeliveryService;
use Willy\Service\ImageProcessor;
use Willy\Service\JobDispatcher;
use Willy\Service\JobFiles;
use Willy\Service\JobProcessor;
use Willy\Service\LoginRateLimiter;
use Willy\Service\MailSender;
use Willy\Service\PdfBuilder;
use Willy\Service\TelegramSender;
use Willy\Service\UploadService;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\HttpClient;
use Willy\Support\Json;
use Willy\Support\Logger;
use Willy\Support\PasswordHasher;
use Willy\Support\Paths;
use Willy\Support\Process;
use Willy\Support\SystemClock;
use Willy\Support\SystemUploadGuard;

final class Container
{
    private function __construct(
        public readonly Config $config,
        public readonly Paths $paths,
        public readonly Clock $clock,
        public readonly FileSystem $files,
        public readonly Json $json,
        public readonly Logger $logger,
        public readonly PasswordHasher $hasher,
        public readonly JobStore $jobs,
        public readonly JobFiles $jobFiles,
        public readonly ImageProcessor $images,
        public readonly PdfBuilder $pdf,
        public readonly TelegramSender $telegram,
        public readonly MailSender $mail,
        public readonly DeliveryService $delivery,
        public readonly JobProcessor $processor,
        public readonly JobDispatcher $dispatcher,
        public readonly CleanupService $cleanup,
        public readonly LoginRateLimiter $limiter,
        public readonly UploadService $uploads,
        public readonly Process $process,
    ) {
    }

    public static function create(string $root): self
    {
        $config = Config::load($root);
        $paths = new Paths($config->root, $config->storagePath);
        $clock = SystemClock::fromTimezone($config->timezone);
        $random = new Randomizer();
        $files = new FileSystem($random);
        $files->ensurePaths($paths);
        $json = new Json($files);
        $logger = new Logger($paths, $files, $json, $clock);
        $jobs = new JobStore($paths, $files, $json, $clock, $random);
        $jobFiles = new JobFiles($paths, $files, $config);
        $layout = new PdfLayout();
        $images = new ImageProcessor($files, $config);
        $pdf = new PdfBuilder($layout, $files, $clock);
        $mail = new MailSender();
        $telegram = new TelegramSender(new HttpClient(), $json);
        $delivery = new DeliveryService($config, $jobs, $files, $telegram, $mail, $clock, $logger);
        $process = new Process();
        $processor = new JobProcessor(
            $jobs,
            $jobFiles,
            $images,
            $pdf,
            $delivery,
            $paths,
            $clock,
            $logger,
        );

        return new self(
            config: $config,
            paths: $paths,
            clock: $clock,
            files: $files,
            json: $json,
            logger: $logger,
            hasher: new PasswordHasher(),
            jobs: $jobs,
            jobFiles: $jobFiles,
            images: $images,
            pdf: $pdf,
            telegram: $telegram,
            mail: $mail,
            delivery: $delivery,
            processor: $processor,
            dispatcher: new JobDispatcher($config, $paths, $process, $logger),
            cleanup: new CleanupService($paths, $files, $jobs, $clock, $config, $logger),
            limiter: new LoginRateLimiter($paths, $json, $clock),
            uploads: new UploadService(
                $config,
                $jobs,
                $jobFiles,
                $images,
                $files,
                $clock,
                new SystemUploadGuard(),
            ),
            process: $process,
        );
    }
}
