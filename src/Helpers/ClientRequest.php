<?php

namespace AnyChat\Helpers;

use GuzzleHttp\Client;

class ClientRequest
{
    protected $timeout = 30; // 30 seconds
    protected $client;
    protected \AnyChat\Client $clientMessage;

    private string $messageHost = 'https://msg-bussiness.tabtab.me';

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
                    'Authorization' => 'Bearer ' . $this->clientMessage->getAccessToken()
                ], $headers),
                'timeout' => $this->timeout,
            ];

            // json data
            if (!empty($data)) {
                $options['json'] = $data;
            }

            // sent request
            $response = $this->client->request($method, $this->messageHost . $uri, $options);
            $statusCode = $response->getStatusCode();
            $isSuccess = true;
            if ($statusCode < 200 || $statusCode >= 400) {
                $isSuccess = false;
            }


            return [
                'is_success' => $isSuccess,
                'status' => $statusCode,
                'data' => json_decode($response->getBody()),
            ];

        } catch (\Throwable $e) {
            return [
                'is_success' => false,
                'status' => $e->getCode(),
                'data' => $e->getMessage()
            ];
        }
    }

    function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }
}
