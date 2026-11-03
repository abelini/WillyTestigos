<?php

declare(strict_types=1);

namespace Willy\Service;

use CurlFile;
use SplFileInfo;
use Willy\Exception\DeliveryException;
use Willy\Support\HttpClient;
use Willy\Support\Json;
use Willy\Support\TelegramConfig;

use function array_key_exists;
use function is_string;

final class TelegramSender
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Json $json,
    ) {
    }

    public function send(TelegramConfig $config, string $pdfPath, string $caption): void
    {
        $info = new SplFileInfo($pdfPath);

        if (!$info->isFile()) {
            throw new DeliveryException('El PDF ya no está disponible para enviarlo a Telegram.');
        }

        $file = new CurlFile($info->getPathname(), 'application/pdf', $info->getBasename());
        $response = $this->http->postMultipart(
            'https://api.telegram.org/bot' . $config->token . '/sendDocument',
            [
                'chat_id' => $config->chatId,
                'caption' => $caption,
            ],
            $file,
            'document',
            300,
        );

        if (!$response->isSuccess()) {
            throw new DeliveryException(
                'Telegram respondió con el estado HTTP ' . $response->status . '.',
            );
        }

        $payload = $this->json->decode($response->body);

        if (!array_key_exists('ok', $payload) || $payload['ok'] !== true) {
            throw new DeliveryException($this->description($payload));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function description(array $payload): string
    {
        if (!array_key_exists('description', $payload) || !is_string($payload['description'])) {
            return 'Telegram rechazó el envío del documento.';
        }

        return 'Telegram: ' . $payload['description'];
    }
}
