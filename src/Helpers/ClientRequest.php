<?php

namespace AnyChat\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class ClientRequest
{
    protected $timeout = 30; // 30 seconds
    protected $client;
    protected \AnyChat\Client $clientMessage;

    private string $messageHost = 'https://8b7e-2402-800-61ca-7bdb-31cd-19a5-7c0d-146d.ngrok-free.app';

    public function __construct(\AnyChat\Client $client)
    {
        $this->client = new Client();
        $this->clientMessage = $client;
    }
    /**
     * Request url
     *
     * @param string $uri
     * @param string $method
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function sent(string $uri, string $method = 'GET', array $data = [], array $headers = []): array
    {
        try {
            $options = [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                    'Authorization' => $this->clientMessage->getAccessToken()
                ], $headers),
                'timeout' => $this->timeout,
            ];

            // with files
            if (!empty($data['files'])) {
                $options['multipart'] = [];
                foreach ($data['files'] as $key => $filePath) {
                    $options['multipart'][] = [
                        'name' => $key,
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath)
                    ];
                }

                unset($data['files']);
            }

            // json data
            if (!empty($data)) {
                if (!isset($options['multipart'])) {
                    // with JSON
                    $options['json'] = $data;
                } else {
                    // with other data multipart
                    foreach ($data as $key => $value) {
                        $options['multipart'][] = [
                            'name' => $key,
                            'contents' => $value
                        ];
                    }
                }
            }

            // sent request
            $response = $this->client->request($method, $this->messageHost . $uri, $options);

            return [
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getBody()),
            ];

        } catch (\Throwable $e) {
            return [
                'status' => $e->getCode(),
                'error' => $e->getMessage()
            ];
        }
    }

    function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }
}
