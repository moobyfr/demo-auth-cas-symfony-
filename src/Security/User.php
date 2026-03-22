<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    /**
     * @param array<string, mixed> $attributes
     * @param string[] $roles
     */
    public function __construct(
        private readonly string $userIdentifier,
        private readonly array $attributes = [],
        private readonly array $roles = ['ROLE_USER'],
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function eraseCredentials(): void
    {
        // No sensitive data stored
    }
}
