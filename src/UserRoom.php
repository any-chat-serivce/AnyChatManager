<?php
namespace AnyChat;

class UserRoom {
    private string $clientId;
    private string $clientSecret;
    private string $roomId;
    private int $expire = 3600 * 24 * 7;
    private array $users = [];
    public function __construct(string $clientId,string $clientSecret,string $roomId)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->roomId = $roomId;
    }

    public function setExpire(int $time): static
    {
        $this->expire = $time;
        return $this;
    }

    /**
     * @param array $users
     * @return array
     * @throws \Exception
     */
    public function setUsers(array $users): array
    {
        foreach ($users as $user) {
            if (!isset($user['id'])) {
                throw new \Exception('User id is required');
            }
            $client = new Client($this->clientId, $this->clientSecret);
            $client->setExpire($user['expire'] ?? $this->expire);
            $options = [
                'room_id' => $this->roomId,
                'user_id' => $user['id'],
                'can_edit' => $user['can_edit'] ?? true,
                'can_delete' => $user['can_delete'] ?? true,
                'can_view' => $user['can_view'] ?? true,
                'can_create' => $user['can_create'] ?? true,
            ];

            $client->setOptions($options);
            $options['access_token'] = $client->getAccessToken();
            $this->users[$user["id"]] = $options;
        }

        return $this->users;
    }
    public function setUser($userId, $canAdd = true, $canView = true, $canDelete = true, $canEdit = true): array
    {
        $client = new Client($this->clientId, $this->clientSecret);
        $client->setExpire($this->expire);
        $options = [
            'room_id' => $this->roomId,
            'user_id' => $userId,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_view' => $canView,
            'can_create' => $canAdd,
        ];

        $client->setOptions($options);
        $options['access_token'] = $client->getAccessToken();
        $this->users[$userId] = $options;

        return $options;
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function getUser($userId): array
    {
        return $this->users[$userId];
    }
}