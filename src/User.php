<?php

namespace AnyChat;

use AnyChat\Helpers\ClientRequest;
use AnyChat\Interfaces\UserInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class User implements UserInterface
{
    private Client $client;
    private $clientRequest;
    protected $fields = [];
    const TYPE_GENDER_MALE = 'MALE';
    const TYPE_GENDER_FEMALE = 'FEMALE';
    const TYPE_GENDER_OTHER = 'OTHER';

    const LIST_TYPE_GENDER = [
        self::TYPE_GENDER_MALE,
        self::TYPE_GENDER_FEMALE,
        self::TYPE_GENDER_OTHER,
    ];

    public function __construct(array $data = [])
    {
        $this->fields = $data;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        }

        return null;
    }

    public function __set($name, $value)
    {
        $this->fields[$name] = $value;
    }

    public function __isset($name) {
        return isset($this->fields[$name]) && $this->fields[$name] !== null;
    }

    public static function getListGender(): array
    {
        return self::LIST_TYPE_GENDER;
    }

    /**
     * Update or create user with message provider
     * @throws Exception
     */
    public function upsertUser(): static
    {
        $this->setupClientRequest();
        if (!$this->_id) {
            $response = $this->create();
        } else {
            $response = $this->update();
        }

        if ($response['status'] < 200 && $response['status'] >= 400) {
            throw new Exception('Update or create user failed');
        }

        $this->fields = json_decode(json_encode($response['body'] ?? []), true);

        return $this;
    }

    /**
     * Sync user with user in message provider
     * @throws Exception
     */
    public function syncOrCreateUser(): static
    {
        $this->setupClientRequest();
        if (!$this->_id) {
            $response = $this->create();
        } else {
            $response = $this->detail();
        }

        if ($response['status'] < 200 && $response['status'] >= 400) {
            throw new Exception('Syncing user failed');
        }

        $this->fields = json_decode(json_encode($response['body'] ?? []), true);

        return $this;
    }

    /**
     * Create new user
     * @return array
     */
    private function create(): array
    {
        $dataCreate = [
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'full_name' => $this->full_name,
            'gender' => $this->gender
        ];

        return $this->clientRequest->sent('/api/user', 'POST', $dataCreate);
    }

    /**
     * Update user
     * @return array
     */
    private function update(): array
    {
        $dataUpdate = [
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'full_name' => $this->full_name,
            'gender' => $this->gender
        ];

        return $this->clientRequest->sent("/api/user/$this->_id", 'PUT', $dataUpdate);
    }

    /**
     * Get user
     */
    private function detail(): array
    {
        return $this->clientRequest->sent("/api/user/$this->_id");
    }

    /**
     * Update user
     * @return mixed
     * @throws Exception
     */
    public function delete(): mixed
    {
        $this->setupClientRequest();
        $response = $this->clientRequest->sent("/api/user/$this->_id", 'DELETE');
        if ($response['status'] < 200 && $response['status'] >= 400) {
            throw new Exception('Delete user failed');
        }

        return $response['body'] ?? [];
    }

    /**
     * Get list users
     * @param $clientId
     * @param $clientSecret
     * @return array
     */
    public static function getAllUsers($clientId, $clientSecret): array
    {
        $client = new Client($clientId, $clientSecret);
        $clientRequest = new ClientRequest($client);

        $response = $clientRequest->sent('/api/user');
        if ($response['status'] < 200 && $response['status'] >= 400) {
            throw new Exception('Delete user failed');
        }

        return $response['body'] ?? [];
    }

    public function setClient($clientId, $clientSecret): static
    {
        $this->client = new Client($clientId, $clientSecret);

        return $this;
    }

    /**
     * @throws Exception
     */
    private function setupClientRequest(): void
    {
        if (empty($this->client)) {
            throw new Exception('Have to setup client first !');
        }

        $this->clientRequest = new ClientRequest($this->client);
    }

    public function getAttributes(): array
    {
        return $this->fields;
    }
}
