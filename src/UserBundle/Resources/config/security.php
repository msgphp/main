<?php

declare(strict_types=1);

namespace MsgPhp;

use MsgPhp\User\Password\PasswordHashingInterface;
use MsgPhp\User\Infra\Security\{PasswordHashing, SecurityUser, SecurityUserProvider, UserValueResolver};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface as SymfonyPasswordEncoderInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\{inline, ref};

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->set(PasswordHashing::class)
            ->args([
                inline(SymfonyPasswordEncoderInterface::class)
                    ->factory([ref('security.encoder_factory'), 'getEncoder'])
                    ->args([SecurityUser::class]),
            ])
        ->alias(PasswordHashingInterface::class, PasswordHashing::class)

        ->set(SecurityUserProvider::class)
        ->set(UserValueResolver::class)
    ;
};
