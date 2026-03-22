<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Point d'entree a adapter si ton projet utilise deja une entite Doctrine.
 *
 * Exemple d'integration:
 * - injecter un repository + EntityManager dans le constructeur
 * - remplacer loadFromCas() pour faire un "find or create" sur ton entite
 * - persister/flush si creation ou mise a jour
 *
 * Exemple rapide:
 * $user = $this->repository->findOneBy(['login' => $identifier]) ?? new AppUser();
 * $user->setLogin($identifier);
 * $user->setEmail($attributes['mail'] ?? null);
 * $user->setRoles($roles);
 * $this->entityManager->persist($user);
 * $this->entityManager->flush();
 * return $user;
 */
class UserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new User($identifier);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param string[] $roles
     */
    public function loadFromCas(string $identifier, array $attributes, array $roles = ['ROLE_USER']): UserInterface
    {
        return new User($identifier, $attributes, $roles);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Unsupported user class: %s', $user::class));
        }

        // User state comes from the CAS-authenticated token/session.
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }
}
