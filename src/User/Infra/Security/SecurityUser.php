<?php

declare(strict_types=1);

namespace MsgPhp\User\Infra\Security;

use MsgPhp\User\Entity\User;
use MsgPhp\User\Password\PasswordProtectedInterface;
use MsgPhp\User\UserIdInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class SecurityUser implements UserInterface, EquatableInterface
{
    /**
     * @var UserIdInterface
     */
    private $id;

    /**
     * @var string|null
     */
    private $originUsername;

    /**
     * @var string[]
     */
    private $roles;

    /**
     * @var string|null
     */
    private $password;

    /**
     * @var string|null
     */
    private $passwordSalt;

    /**
     * @param string[] $roles
     */
    public function __construct(User $user, string $originUsername = null, array $roles = [])
    {
        $this->id = $user->getId();

        if ($this->id->isEmpty()) {
            throw new \LogicException('The user ID cannot be empty.');
        }

        $this->originUsername = $originUsername;
        $this->roles = $roles;

        $credential = $user->getCredential();

        if ($credential instanceof PasswordProtectedInterface) {
            $this->password = $credential->getPassword();
            $this->passwordSalt = $credential->getPasswordAlgorithm()->salt->token ?? null;
        }
    }

    public function getUserId(): UserIdInterface
    {
        return $this->id;
    }

    public function getOriginUsername(): ?string
    {
        return $this->originUsername;
    }

    public function getUsername(): string
    {
        return $this->id->toString();
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function getSalt(): ?string
    {
        return $this->passwordSalt;
    }

    public function eraseCredentials(): void
    {
        $this->password = $this->passwordSalt = null;
    }

    public function isEqualTo(UserInterface $user)
    {
        return $user instanceof self && $user->getUserId()->equals($this->id);
    }
}
