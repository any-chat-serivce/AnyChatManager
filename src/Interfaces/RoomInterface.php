<?php

namespace AnyChat\Interfaces;

interface RoomInterface
{

    /**
     * Update or create room with config
     * @return RoomInterface
     */
    public function upsertRoom(): static;

    /**
     * Create or sync room with
     * @return RoomInterface
     */
    public function syncOrCreateRoom(): static;

    public function addUsers(array $userInputs): static;

    public function addUser(array $userInput, $canAdd = true, $canView = true, $canDelete = true, $canEdit = true): static;
}