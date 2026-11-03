<?php

declare(strict_types=1);

namespace Willy\Support;

use Throwable;
use Uri\Rfc3986\Uri;
use Willy\Exception\ConfigurationException;

use function in_array;
use function is_string;
use function preg_match;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function timezone_identifiers_list;
use function trim;

final class Config
{
    private function __construct(
        public readonly string $root,
        public readonly string $appEnv,
        public readonly string $appUrl,
        public readonly string $timezone,
        public readonly string $passwordHash,
        public readonly string $coverSubtitle,
        public readonly string $storagePath,
        public readonly int $maxFiles,
        public readonly int $maxFileMb,
        public readonly int $maxTotalMb,
        public readonly int $maxPdfMb,
        public readonly int $jpegQuality,
        public readonly string $workerMode,
        public readonly string $phpBinary,
        public readonly string $telegramToken,
        public readonly string $telegramChatId,
        public readonly string $mailHost,
        public readonly int $mailPort,
        public readonly string $mailUser,
        public readonly string $mailPass,
        public readonly string $mailEncryption,
        public readonly string $mailFrom,
        public readonly string $mailFromName,
        public readonly string $mailTo,
        public readonly bool $sessionSecure,
        public readonly string $sessionName,
    ) {
    }

    public static function load(string $root): self
    {
        Env::load($root);

        $appUrl = Env::required('APP_URL');

        try {
            $uri = Uri::parse($appUrl);
        } catch (Throwable $exception) {
            throw new ConfigurationException('APP_URL no es una URL válida.');
        }

        if (!$uri instanceof Uri) {
            throw new ConfigurationException('APP_URL no es una URL válida.');
        }

        $scheme = $uri->getScheme();

        if (!is_string($scheme) || !in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException('APP_URL debe usar http o https.');
        }

        $appEnv = Env::string('APP_ENV', 'production');

        if (!in_array($appEnv, ['production', 'development', 'testing'], true)) {
            throw new ConfigurationException('APP_ENV debe ser production, development o testing.');
        }

        $timezone = Env::string('APP_TIMEZONE', 'America/Mazatlan');

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ConfigurationException('APP_TIMEZONE no es una zona horaria válida.');
        }

        $workerMode = Env::string('WORKER_MODE', 'auto');

        if (!in_array($workerMode, ['auto', 'background', 'inline'], true)) {
            throw new ConfigurationException('WORKER_MODE debe ser auto, background o inline.');
        }

        $mailEncryption = strtolower(Env::string('MAIL_ENCRYPTION', 'tls'));
        $mailEncryption = match ($mailEncryption) {
            'starttls' => 'tls',
            'smtps' => 'ssl',
            'none', 'off' => '',
            default => $mailEncryption,
        };

        if (!in_array($mailEncryption, ['', 'tls', 'ssl'], true)) {
            throw new ConfigurationException('MAIL_ENCRYPTION debe ser tls, ssl, starttls, smtps, none o vacío.');
        }

        $storagePath = self::absolutePath($root, Env::string('STORAGE_PATH', 'storage'));

        return new self(
            root: rtrim(str_replace('\\', '/', $root), '/'),
            appEnv: $appEnv,
            appUrl: $appUrl,
            timezone: $timezone,
            passwordHash: Env::required('APP_PASSWORD_HASH'),
            coverSubtitle: Env::string('COVER_SUBTITLE', 'Portal: Proyecto Z'),
            storagePath: $storagePath,
            maxFiles: Env::int('MAX_FILES', 200, 1, 500),
            maxFileMb: Env::int('MAX_FILE_MB', 25, 1, 100),
            maxTotalMb: Env::int('MAX_TOTAL_MB', 1024, 1, 4096),
            maxPdfMb: Env::int('MAX_PDF_MB', 16, 5, 50),
            jpegQuality: Env::int('JPEG_QUALITY', 82, 40, 95),
            workerMode: $workerMode,
            phpBinary: Env::string('PHP_CLI_BINARY', PHP_BINARY),
            telegramToken: Env::string('TELEGRAM_BOT_TOKEN', ''),
            telegramChatId: Env::string('TELEGRAM_CHAT_ID', ''),
            mailHost: Env::string('MAIL_HOST', ''),
            mailPort: Env::int('MAIL_PORT', 587, 1, 65535),
            mailUser: Env::string('MAIL_USER', ''),
            mailPass: Env::string('MAIL_PASS', ''),
            mailEncryption: $mailEncryption,
            mailFrom: Env::string('MAIL_FROM', ''),
            mailFromName: Env::string('MAIL_FROM_NAME', 'Testigos'),
            mailTo: Env::string('MAIL_TO', ''),
            sessionSecure: $scheme === 'https',
            sessionName: 'willy_session',
        );
    }

    public function hasTelegram(): bool
    {
        return $this->telegramToken !== '' && $this->telegramChatId !== '';
    }

    public function telegram(): TelegramConfig
    {
        if (!$this->hasTelegram()) {
            throw new ConfigurationException('Telegram no está configurado.');
        }

        return new TelegramConfig($this->telegramToken, $this->telegramChatId);
    }

    public function hasMail(): bool
    {
        return $this->mailHost !== ''
            && $this->mailUser !== ''
            && $this->mailPass !== ''
            && $this->mailTo !== '';
    }

    public function mail(): MailConfig
    {
        if (!$this->hasMail()) {
            throw new ConfigurationException('El correo no está configurado.');
        }

        $from = $this->mailFrom === '' ? $this->mailUser : $this->mailFrom;

        return new MailConfig(
            host: $this->mailHost,
            port: $this->mailPort,
            user: $this->mailUser,
            pass: $this->mailPass,
            encryption: $this->mailEncryption,
            from: $from,
            fromName: $this->mailFromName,
            to: $this->mailTo,
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }

    public function maxFileBytes(): int
    {
        return $this->maxFileMb * 1024 * 1024;
    }

    public function maxTotalBytes(): int
    {
        return $this->maxTotalMb * 1024 * 1024;
    }

    public function maxPdfBytes(): int
    {
        return $this->maxPdfMb * 1024 * 1024;
    }

    public function maxPixels(): int
    {
        return 80_000_000;
    }

    public function pdfRetentionSeconds(): int
    {
        return 3600;
    }

    public function failureRetentionSeconds(): int
    {
        return 86400;
    }

    public function staleJobSeconds(): int
    {
        return 86400;
    }

    private static function absolutePath(string $root, string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if (str_starts_with($normalized, '/')) {
            return rtrim($normalized, '/');
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return rtrim($normalized, '/');
        }

        return rtrim(str_replace('\\', '/', $root), '/') . '/' . trim($normalized, '/');
    }
}
