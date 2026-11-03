<?php

declare(strict_types=1);

namespace Willy\Support;

use CurlFile;
use CurlHandle;
use Willy\Exception\DeliveryException;

use function curl_close;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function is_string;

final class HttpClient
{
    public function get(string $url, int $timeout = 30): HttpResponse
    {
        return $this->request($url, [], false, $timeout);
    }

    /**
     * @param array<string, string> $fields
     */
    public function postForm(string $url, array $fields, int $timeout = 30): HttpResponse
    {
        return $this->request($url, $fields, true, $timeout);
    }

    /**
     * @param array<string, string> $fields
     */
    public function postMultipart(
        string $url,
        array $fields,
        CurlFile $file,
        string $fileField,
        int $timeout = 120,
    ): HttpResponse {
        $payload = $fields;
        $payload[$fileField] = $file;

        return $this->request($url, $payload, true, $timeout);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(string $url, array $payload, bool $post, int $timeout): HttpResponse
    {
        $handle = curl_init($url);

        if (!$handle instanceof CurlHandle) {
            throw new DeliveryException('No se pudo iniciar cURL.');
        }

        try {
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'WillyTestigos/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];

            if ($post) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $payload;
            } else {
                $options[CURLOPT_HTTPGET] = true;
            }

            if (!curl_setopt_array($handle, $options)) {
                throw new DeliveryException('No se pudieron configurar las opciones de cURL.');
            }

            $body = curl_exec($handle);

            if (!is_string($body)) {
                throw new DeliveryException('Error de conexión: ' . curl_error($handle));
            }

            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            return new HttpResponse($status, $body);
        } finally {
            curl_close($handle);
        }
    }
}
