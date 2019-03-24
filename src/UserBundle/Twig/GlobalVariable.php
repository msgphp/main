<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\Twig;

use MsgPhp\User\Infrastructure\Security\SecurityUser;
use MsgPhp\User\Repository\UserRepository;
use MsgPhp\User\User;
use MsgPhp\User\UserId;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class GlobalVariable
{
    public const NAME = 'msgphp_user';

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getCurrent(): User
    {
        return $this->getUserRepository()->find($this->getCurrentId());
    }

    public function getCurrentId(): UserId
    {
        if (null === $token = $this->getTokenStorage()->getToken()) {
            throw new \LogicException('User not authenticated.');
        }

        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            throw new \LogicException('User not authenticated.');
        }

        return $user->getUserId();
    }

    public function isUserType(User $user, string $class): bool
    {
        return $user instanceof $class;
    }

    private function getTokenStorage(): TokenStorageInterface
    {
        if (!$this->container->has(TokenStorageInterface::class)) {
            throw new \LogicException('Token storage not available.');
        }

        return $this->container->get(TokenStorageInterface::class);
    }

    private function getUserRepository(): UserRepository
    {
        if (!$this->container->has(UserRepository::class)) {
            throw new \LogicException('User repository not available.');
        }

        return $this->container->get(UserRepository::class);
    }
}
