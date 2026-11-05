<?php

declare(strict_types=1);

namespace Willy\Http\Controller;

use Willy\Domain\Job;
use Willy\Domain\Month;
use Willy\Http\Request;
use Willy\Http\Response;
use Willy\Repository\JobStore;
use Willy\Service\Auth;
use Willy\Service\CleanupService;
use Willy\Service\WordPressSource;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\Csrf;
use Willy\Support\View;

use function array_map;

final class DashboardController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly JobStore $jobs,
        private readonly CleanupService $cleanup,
        private readonly Config $config,
        private readonly Clock $clock,
        private readonly WordPressSource $wordpress,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }

        $this->cleanup->maybeRun();

        $jobs = $this->jobs->all();
        $detected = Month::detect($this->clock->now());

        $content = View::render('dashboard', [
            'csrf' => $this->csrf->token(),
            'months' => Month::all(),
            'detected' => $detected,
            'jobs' => array_map(fn (Job $job): array => $this->jobView($job), $jobs),
            'max_files' => $this->config->maxFiles,
            'max_file_mb' => $this->config->maxFileMb,
            'wordpress' => $this->config->hasWordPress(),
            'wp_daily' => $this->config->wpDailyLimit,
            'wp_term' => $this->config->wpSearchTerm,
        ]);

        return Response::html(View::render('layout', [
            'title' => 'Testigos',
            'content' => $content,
            'config' => View::json([
                'csrf' => $this->csrf->token(),
                'detected' => $detected->value,
                'maxFiles' => $this->config->maxFiles,
                'maxFileMb' => $this->config->maxFileMb,
                'pollMs' => 2000,
            ]),
        ]));
    }

    /**
     * @param array<string, string> $params
     */
    public function state(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();

        return Response::json([
            'ok' => true,
            'detected' => Month::detect($this->clock->now())->value,
            'jobs' => array_map(
                fn (Job $job): array => $job->toView($this->clock),
                $this->jobs->all(),
            ),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): Response
    {
        $this->auth->requireAuthentication();
        $this->csrf->verify($request->csrfToken());

        $month = Month::fromNumber(
            $request->postInt('month', Month::detect($this->clock->now())->number()),
        );

        $job = $this->jobs->create($month, $this->config->coverSubtitle);

        return Response::json([
            'ok' => true,
            'job' => $job->toView($this->clock),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobView(Job $job): array
    {
        $view = $job->toView($this->clock);
        $summary = $this->wordpress->summary($job->id);

        $view['source'] = $summary['source'];
        $view['wp_selected'] = $summary['selected'];
        $view['wp_rendered'] = $summary['rendered'];

        return $view;
    }
}
