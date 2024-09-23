<?php

namespace AnyChat\Interfaces;

interface UserInterface
{
    /**
     * Update or create user with user message
     * @return UserInterface
     */
    public function upsertUser(): self;

    /**
     * Create or sync data if exists
     * @return self
     */
    public function syncOrCreateUser(): self;
}
