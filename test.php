<?php
namespace AnyChat;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Client.php';
require_once __DIR__ . '/src/UserRoom.php';

// example 1
$client = new Client('client_id', 'client_secret');
echo $client->getAccessToken() . PHP_EOL;



// example 2
$room = new UserRoom('client_id', 'client_secret', 'room_id');
$room->setExpire(3600 * 24 * 7);
$users = $room->setUsers([
    [
        'id' => 'user_2',
        'can_edit' => true,
        'can_delete' => true,
        'can_view' => true,
        'can_create' => true,
    ],
    [
        'id' => 'user_1',
        'can_edit' => true,
        'can_delete' => true,
        'can_view' => true,
        'can_create' => true,
    ],
]);

print_r($users);