<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\DependencyInjection\Bundle;

use MsgPhp\Domain\Infra\DependencyInjection\Compiler;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class BundleHelper
{
    public static function initDomain(ContainerBuilder $container): void
    {
        ContainerHelper::addCompilerPassOnce($container, Compiler\ResolveDomainPass::class);

        if (ContainerHelper::isDoctrineOrmEnabled($container)) {
            $container->setParameter('msgphp.doctrine.mapping_cache_dirname', 'msgphp/doctrine-mapping');

            ContainerHelper::addCompilerPassOnce($container, Compiler\DoctrineObjectFieldMappingPass::class);
        }
    }

    public static function initDoctrineTypes(Container $container): void
    {
        static $prepared = false;

        if ($prepared || !$container->hasParameter($param = 'msgphp.doctrine.type_config')) {
            return;
        }

        foreach ($container->getParameter($param) as $config) {
            $config['type']::setClass($config['class']);
            $config['type']::setDataType($config['data_type']);
        }

        $prepared = true;
    }

    private function __construct()
    {
    }
}
