<?php

declare(strict_types=1);

namespace Willy\Tests;

use Random\Randomizer;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\Json;
use Willy\Support\Paths;
use Willy\Support\SystemClock;

use function dirname;
use function implode;

final class TestEnvironment
{
    private static string $root = '';

    public static function root(): string
    {
        if (self::$root === '') {
            self::$root = dirname(__DIR__) . '/storage/testing';
            self::files()->ensureDirectory(self::$root);
            self::files()->writeAtomic(
                self::$root . '/.env',
                implode("\n", [
                    'APP_ENV=testing',
                    'APP_URL=http://localhost',
                    'APP_TIMEZONE=America/Mazatlan',
                    'APP_PASSWORD_HASH=$2y$12$abcdefghijklmnopqrstuv',
                    'COVER_SUBTITLE="Portal: Proyecto Z"',
                    'STORAGE_PATH=storage',
                    'WORKER_MODE=inline',
                    'TELEGRAM_BOT_TOKEN=',
                    'TELEGRAM_CHAT_ID=',
                    'MAIL_HOST=',
                    'MAIL_USER=',
                    'MAIL_PASS=',
                    'MAIL_TO=',
                ]) . "\n",
            );
        }

        return self::$root;
    }

    public static function config(): Config
    {
        return Config::load(self::root());
    }

    public static function files(): FileSystem
    {
        return new FileSystem(new Randomizer());
    }

    public static function json(): Json
    {
        return new Json(self::files());
    }

    public static function paths(): Paths
    {
        $config = self::config();

        return new Paths($config->root, $config->storagePath);
    }

    public static function clock(): SystemClock
    {
        return SystemClock::fromTimezone('America/Mazatlan');
    }

    public static function resetJobs(): void
    {
        $files = self::files();
        $paths = self::paths();
        $files->ensurePaths($paths);
        $files->deleteContents($paths->jobs());
    }
}
