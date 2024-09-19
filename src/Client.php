<?php
namespace AnyChat;

use Firebase\JWT\JWT;

class Client {
    private string $clientId;
    private string $clientSecret;
    private int $expire = 3600 * 24 * 7;
    private array $options = [];
    public function __construct(string $clientId,string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function setExpire(int $time): static
    {
        $this->expire = $time;
        return $this;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getAccessToken(): string
    {
        $now = time();
        $tokenPayload = array_merge($this->options, [
            'client_id' => $this->clientId,
            'iat' => $now, // Issued at
            'exp' => $now + $this->expire,
        ]);

        return JWT::encode($tokenPayload, $this->clientSecret, 'HS256');
    }
}