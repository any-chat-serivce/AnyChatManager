<?php

namespace AnyChat;

use AnyChat\Helpers\ClientRequest;
use AnyChat\Interfaces\RoomInterface;
use Exception;

class Room implements RoomInterface
{
    public string|null $id;
    public string|null $name;
    public string|null $avatar;
    public string|null $expiredTime;
    public mixed $descriptionConfig = null;
    public mixed $description = null;
    public array $users = [];
    public array $pins = [];

    private string|null $clientId;
    private string|null $clientSecret;
    private ClientRequest $clientRequest;

    public function __construct(array $data)
    {
        $this->setData($data);
    }

    /**
     * Set data for room
     * @param array $data
     * @return void
     * @throws Exception
     */
    private function setData(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->expiredTime = $data['expired_time'] ?? null;
        $this->avatar = $data['avatar'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->descriptionConfig = $data['description_config'] ?? $this->descriptionConfig;
        $this->pins = $data['pins'] ?? [];

        // user
        if (!empty($data['user_ids'])) {
            $this->setUser($data['user_ids']);
        }
    }

    private function getData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'expired_time' => $this->expiredTime,
            'description' => $this->description,
            'description_config' => $this->descriptionConfig,
            'pins' => $this->pins,
            'user_ids' => $this->users,
        ];
    }

    /**
     * Update or create room with message provider
     * @return static
     * @throws Exception
     */
    public function upsertRoom(): static
    {
        $this->setupClientRequest();
        if (!$this->id) {
            $response = $this->create();
        } else {
            $response = $this->update();
        }

        if (!$response['is_success']) {
            throw new Exception('Get room failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        $data = json_decode(json_encode($response['data'] ?? []), true);
        $this->customUsersInRoom($data);
        $id = $data['_id'] ?? null;
        if (empty($id)) {
            throw new Exception('Room not found');
        }

        $data['id'] = $id;
        $this->setData($data);

        return $this;
    }

    /**
     * Sync room or create room with message provider
     * @return static
     * @throws Exception
     */
    public function syncOrCreateRoom(): static
    {
        $this->setupClientRequest();
        if (!$this->id) {
            $response = $this->create();
        } else {
            $response = $this->detail();
        }

        if (!$response['is_success']) {
            throw new Exception('Get room failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        $data = json_decode(json_encode($response['data'] ?? []), true);
        $this->customUsersInRoom($data);
        $id = $data['_id'] ?? null;
        if (empty($id)) {
            throw new Exception('Room not found');
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
        $userIds = array_map(fn($userRoom) => $userRoom['id'], $this->users);
        $dataCreate = [
            'user_ids' => $userIds,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'expired_time' => $this->expiredTime,
            'description' => $this->descriptionConfig,
        ];

        return $this->clientRequest->sent('/api/room', 'POST', $dataCreate);
    }

    /**
     * Update room
     * @return array
     */
    private function update(): array
    {
        $userIds = array_map(fn($userRoom) => $userRoom['id'], $this->users);
        $dataUpdate = [
            'user_ids' => $userIds,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'expired_time' => $this->expiredTime,
            'description' => $this->descriptionConfig,
            'pins' => $this->pins
        ];

        return $this->clientRequest->sent("/api/room/$this->id", 'PUT', $dataUpdate);
    }

    /**
     * Get room
     * @throws Exception
     */
    private function detail(): array
    {
        if (empty($this->id)) {
            throw new Exception('Room id is required');
        }

        return $this->clientRequest->sent("/api/room/$this->id");
    }

    /**
     * Auto generate permissions for user existed and rom existed
     * @return RoomInterface
     * @throws Exception
     */
    public function syncPermissionUser(): static
    {
        $this->checkClient();
        $this->addUsers($this->users);

        return $this;
    }

    /**
     * Update user
     * @return mixed
     * @throws Exception
     */
    public function delete(): mixed
    {
        if (empty($this->id)) {
            throw new Exception('Room id is required');
        }
        $this->setupClientRequest();
        $response = $this->clientRequest->sent("/api/room/$this->id", 'DELETE');
        if (!$response['is_success']) {
            throw new Exception('Delete room failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        return $response['data'] ?? [];
    }

    public function addUser(array $userInput, $canAdd = true, $canView = true, $canDelete = true, $canEdit = true): static
    {
        $userId = $userInput['id'] ?? null;
        $userRoom = $userInput['user_room'] ?? [];
        $newUserRoom = array_merge(['user_id' => $userId], $userInput['user_room'] ?? []);
        if ($this->isSetupClient() && !empty($this->id) && !empty($userId)) {
            // access permissions in field user_room
            $canAdd = $userRoom['can_create'] ?? $canAdd;
            $canView = $userRoom['can_view'] ?? $canView;
            $canDelete = $userRoom['can_delete'] ?? $canDelete;
            $canEdit = $userRoom['can_edit'] ?? $canEdit;
            $newUserRoom = (new UserRoom($this->clientId, $this->clientSecret, $this->id))->setUser($userId, $canAdd, $canView, $canDelete, $canEdit);
        }

        $attributeUser = (new User($userInput))->getAttributes();
        $attributeUser['user_room'] = $newUserRoom;
        $existed = array_filter($this->users, fn($user) => $user['id'] == $userId);
        if (empty($existed)) {
            $this->users[] = $attributeUser;
        } else {
            // update older users
            $existedIndex = array_key_first($existed);
            $this->users[$existedIndex] = $attributeUser;
        }

        return $this;
    }

    /**
     * Add users for room
     * Set permissions in field user_room for each user
     * @param array $userInputs
     * @return $this
     */
    public function addUsers(array $userInputs): static
    {
        foreach ($userInputs as $userInput) {
            $this->addUser($userInput);
        }

        return $this;
    }

    /**
     * Setup client request
     * @return $this
     * @throws Exception
     */
    private function setupClientRequest(): static
    {
        $this->checkClient();
        $client = new Client($this->clientId, $this->clientSecret);
        $this->clientRequest = new ClientRequest($client);

        return $this;
    }

    /**
     * Check setup client
     * @return bool
     */
    private function isSetupClient(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Set client
     * @param string $clientId
     * @param string $clientSecret
     * @return $this
     */
    public function setClient(string $clientId, string $clientSecret): static
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * Check Client setup
     * @throws Exception
     */
    public function checkClient(): static
    {
        if (!$this->isSetupClient()) {
            throw new Exception('Client not setup');
        }

        return $this;
    }

    /**
     * Set users
     * @throws Exception
     */
    private function setUser(array $users): void
    {
        if (empty($users[0]['id']) && is_string($users[0])) {
            $this->addUserWithIds($users);

            return;
        }

        $this->addUsers($users);
    }

    /**
     * Add user room with user ids
     * @param array $userIds
     * @return $this
     * @throws Exception
     */
    public function addUserWithIds(array $userIds): static
    {
        foreach ($userIds as $userId) {
            $existed = array_filter($this->users, fn($user) => $user['id'] == $userId);
            if (empty($existed)) {
                $newUserRoom = ['user_id' => $userId];
                if ($this->isSetupClient() && !empty($this->id)) {
                    $newUserRoom = (new UserRoom($this->clientId, $this->clientSecret, $this->id))->setUser($userId);
                }

                $attributeUser = (new User(['id' => $userId]))->getAttributes();
                $attributeUser['user_room'] = $newUserRoom;
                $this->users[] = $attributeUser;
            }
        }

        return $this;
    }

    /**
     * Get list rooms
     * @param $clientId
     * @param $clientSecret
     * @return array
     * @throws Exception
     */
    public static function getAllRooms($clientId, $clientSecret): array
    {
        $client = new Client($clientId, $clientSecret);
        $clientRequest = new ClientRequest($client);
        $response = $clientRequest->sent('/api/room');
        if (!$response['is_success']) {
            throw new Exception('Get list room failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        $data = $response['data']->data ?? [];
        $rooms = [];
        foreach ($data as $room) {
            $room->id = $room->_id;
            $roomCustom = json_decode(json_encode($room), true);
            $userIds = [];
            foreach ($roomCustom['user_ids'] as $user) {
                $user['id'] = $user['_id'] ?? null;
                $userIds[] = $user;
            }
            $roomCustom['user_ids'] = $userIds;
            $rooms[] = (new Room($roomCustom))->getData();
        }

        return $rooms;
    }

    /**
     * Add description with format template
     * Template ID depends on the template we provide
     * Template value depends on the template we provide
     * @example
     * "template_value" => [
     * "$imageUrl" => "url",
     * "$url" => "url",
     * "$title" => "title",
     * "$content1" => "content1",
     * "$content2" => "content1",
     * ]
     * @param array $descriptionConfig
     * @return static
     * @throws Exception
     */
    public function setDescriptionConfig(array $descriptionConfig): static
    {
        if (empty($descriptionConfig['template_id'])) {
            throw new Exception('Template ID is required');
        }

        $this->descriptionConfig = [
            'template_id' => $descriptionConfig['template_id'],
            'template_value' => $descriptionConfig['template_value'],
        ];

        return $this;
    }

    /**
     * Get all attributes
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->getData();
    }

    /**
     * Custom id in users
     * @param array $roomCustom
     * @return void
     */
    private function customUsersInRoom(array &$roomCustom)
    {
        $userIds = [];
        foreach ($roomCustom['user_ids'] as $user) {
            $user['id'] = $user['_id'] ?? null;
            $userIds[] = $user;
        }
        $roomCustom['user_ids'] = $userIds;
    }
}
