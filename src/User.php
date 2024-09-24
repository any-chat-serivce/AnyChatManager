<?php

namespace AnyChat;

use AnyChat\Helpers\ClientRequest;
use AnyChat\Interfaces\UserInterface;
use Exception;

class User implements UserInterface
{
    private Client $client;
    private $clientRequest;
    public string|null $id;
    public string|null $email;
    public string|null $phone;
    public string|null $avatar;
    public string|null $fullName;
    public string|null $gender;

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
        $this->setData($data);
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
        if (!$this->id) {
            $response = $this->create();
        } else {
            $response = $this->update();
        }

        if (!$response['is_success']) {
            throw new Exception('Get user failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        $data = json_decode(json_encode($response['data'] ?? []), true);
        $id = $data['_id'] ?? null;
        if (empty($id)) {
            throw new Exception('User not found');
        }

        $data['id'] = $id;
        $this->setData($data);

        return $this;
    }

    /**
     * Sync user with user in message provider
     * @throws Exception
     */
    public function syncOrCreateUser(): static
    {
        $this->setupClientRequest();
        if (!$this->id) {
            $response = $this->create();
        } else {
            $response = $this->detail();
        }

        if (!$response['is_success']) {
            throw new Exception('Get user failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        $data = json_decode(json_encode($response['data'] ?? []), true);
        $id = $data['_id'] ?? null;
        if (empty($id)) {
            throw new Exception('User not found');
        }

        $data['id'] = $id;
        $this->setData($data);

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
            'full_name' => $this->fullName,
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
            'full_name' => $this->fullName,
            'gender' => $this->gender
        ];

        return $this->clientRequest->sent("/api/user/$this->id", 'PUT', $dataUpdate);
    }

    /**
     * Get user
     */
    private function detail(): array
    {
        return $this->clientRequest->sent("/api/user/$this->id");
    }

    /**
     * Update user
     * @return mixed
     * @throws Exception
     */
    public function delete(): mixed
    {
        $this->setupClientRequest();
        $response = $this->clientRequest->sent("/api/user/$this->id", 'DELETE');
        if (!$response['is_success']) {
            throw new Exception('Get user failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        return $response['data'] ?? [];
    }

    /**
     * Get list users
     * @param $clientId
     * @param $clientSecret
     * @return array
     * @throws Exception
     */
    public static function getAllUsers($clientId, $clientSecret): array
    {
        $client = new Client($clientId, $clientSecret);
        $clientRequest = new ClientRequest($client);
        $response = $clientRequest->sent('/api/user');
        if (!$response['is_success']) {
            throw new Exception('Get user failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        $data =  $response['data'] ?? [];
        $users = [];
        foreach ($data as $user) {
            $user->id = $user->_id;
            $userCustom = json_decode(json_encode($user), true);
            $users[] = (new User($userCustom))->getData();
        }

        return $users;
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
        return $this->getData();
    }

    /**
     * Get token for user with config with follow client
     * @throws Exception
     */
    public function getToken(): string
    {
        if (empty($this->client)) {
            throw new Exception('Have to setup client first !');
        }

        $this->client->setOptions( [
            'id' => $this->id,
            'full_name' => $this->fullName,
        ]);

        return $this->client->getAccessToken();
    }

    private function setData(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->avatar = $data['avatar'] ?? null;
        $this->fullName = $data['full_name'] ?? null;
        $this->gender = $data['gender'] ?? null;
    }

    private function getData(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'full_name' => $this->fullName,
            'gender' => $this->gender,
        ];
    }
}
