<?php

namespace AnyChat;

use AnyChat\Helpers\ClientRequest;
use AnyChat\Interfaces\RoomInterface;
use Exception;

class Room implements RoomInterface
{
    public string|null $id;
    public string|null $name;
    public string|null $title;
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
        $this->title = $data['title'] ?? null;
        $this->expiredTime = $data['expired_time'] ?? null;
        $this->avatar = $data['avatar'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->descriptionConfig = $data['description_config'] ?? $this->descriptionConfig;
        $this->pins = $data['pins'] ?? [];

        // users
        if (!empty($data['users'])) {
            $this->addUsers($data['users']);
        }
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

    public function addUser(array $userInput, $canAdd = true, $canView = true, $canDelete = true, $canEdit = true): static
    {
        $userId = $userInput['id'] ?? null;
        $existed = array_filter($this->users, fn($user) => $user['id'] == $userId);
        if (empty($existed)) {
            $this->users[] = $userInput;
        } else {
            // update older users
            $existedIndex = array_key_first($existed);
            $olderUser = reset($existed);
            $this->users[$existedIndex] = array_merge($olderUser, $userInput);
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
            $users = [];
            foreach ($roomCustom['user_ids'] as $user) {
                $user['id'] = $user['_id'] ?? null;
                $users[] = $user;
            }
            $roomCustom['users'] = $users;
            $rooms[] = (new Room($roomCustom))->getAttributes();
        }

        return $rooms;
    }

    /**
     * Get list rooms
     * @param $clientId
     * @param $clientSecret
     * @return array
     * @throws Exception
     */
    public static function getLastRoomNewMessage($clientId, $clientSecret, $userID): array
    {
        $client = new Client($clientId, $clientSecret);
        $clientRequest = new ClientRequest($client);
        $response = $clientRequest->sent('/api/room/list-rooms?user_id=' . $userID);
        if (!$response['is_success']) {
            throw new Exception('Get list room failed. Status: ' . $response['status'] . ' Error: ' . json_encode($response['data']));
        }

        return $response['data'] ?? [];
    }

    /**
     * Get all attributes
     * @return array
     */
    public function getAttributes(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'avatar' => $this->avatar,
            'expired_time' => $this->expiredTime,
            'description' => $this->description,
            'description_config' => $this->descriptionConfig,
            'pins' => $this->pins,
            'users' => $this->users,
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
     * Check setup client
     * @return bool
     */
    private function isSetupClient(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
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
            'title' => $this->title,
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
            'title' => $this->title,
            'avatar' => $this->avatar,
            'expired_time' => $this->expiredTime,
            'description' => $this->descriptionConfig,
            'pins' => $this->pins
        ];

        return $this->clientRequest->sent("/api/room/$this->id", 'PUT', $dataUpdate);
    }

    /**
     * Custom id in users
     * @param array $room
     * @return void
     */
    private function customUsersInRoom(array &$room): void
    {
        $userIds = [];
        foreach ($room['user_ids'] as $item) {
            if (!isset($item['id']) && !isset($item['_id'])) {
                $user['id'] = $item;
            } else {
                if (isset($item['_id'])) {
                    $item['id'] = $item['_id'];
                }

                $user = $item;
            }

            $userIds[] = $user;
        }

        $room['users'] = $userIds;
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


    public function id(): ?string
    {
        return $this->id;
    }

    public function avatar(): ?string
    {
        return $this->avatar;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function expiredTime(): ?string
    {
        return $this->expiredTime;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getDescriptionConfig()
    {
        return $this->descriptionConfig;
    }

    /**
     * Add description with format template
     * Template ID depends on the template we provide
     * Template value depends on the template we provide
     * @param array $descriptionConfig
     * @return static
     * @throws Exception
     * @example
     * "template_value" => [
     * "$imageUrl" => "url",
     * "$url" => "url",
     * "$title" => "title",
     * "$content1" => "content1",
     * "$content2" => "content1",
     * ]
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

    public function users(): ?array
    {
        return $this->users;
    }

    public function pins(): ?array
    {
        return $this->pins;
    }

    /**
     * @throws Exception
     */
    public function usersWithPermission(): ?array
    {
        $this->checkClient();
        $users = [];
        foreach ($this->users as $user) {
            if (empty($user['id'])) {
                $permission = $this->permissionUser($user['id']) ?? [];
                $users[] = array_merge($user, $permission);
            }

            $users[] = $user;
        }

        return $users;
    }

    /**
     * Get permissions for a user
     * @param string $userId
     * @return array|null
     * @throws Exception
     */
    public function permissionUser(string $userId): ?array
    {
        $this->checkClient();
        $existed = array_filter($this->users, fn($user) => $user['id'] == $userId) ?? [];
        $user = reset($existed);
        if (!empty($user) && !empty($this->id)) {
            // access permissions in field user_room
            $canAdd = $user['can_create'] ?? true;
            $canView = $user['can_view'] ?? true;
            $canDelete = $user['can_delete'] ?? true;
            $canEdit = $user['can_edit'] ?? true;

            return (new UserRoom($this->clientId, $this->clientSecret, $this->id))
                ->setUser($user['id'], $canAdd, $canView, $canDelete, $canEdit);
        }

        return null;
    }

    /**
     * Get all info user by sync data user with message provider
     * @return array|null
     * @throws Exception
     */
    public function usersFullInfo(): ?array
    {
        $this->checkClient();
        $users = [];
        foreach ($this->users as $user) {
            if (!empty($user['id'])) {
                $userSync = (new User($user))->setClient($this->clientId, $this->clientSecret)->syncOrCreateUser()->getAttributes();
                $permission = $this->permissionUser($user['id']) ?? [];
                $users[] = array_merge($user, $userSync, $permission);
                continue;
            }

            $users[] = $user;
        }

        return $users;
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
}
