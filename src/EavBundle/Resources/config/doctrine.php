<?php

declare(strict_types=1);

namespace MsgPhp;

use MsgPhp\Domain\Infra\DependencyInjection\ContainerHelper;
use MsgPhp\Eav\AttributeIdInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @var ContainerBuilder $container */
$container = $container ?? (function (): ContainerBuilder { throw new \LogicException('Invalid context.'); })();
$reflector = ContainerHelper::getClassReflector($container);

return function (ContainerConfigurator $container) use ($reflector): void {
    $baseDir = dirname($reflector(AttributeIdInterface::class)->getFileName());
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->load($ns = 'MsgPhp\\Eav\\Infra\\Doctrine\\Repository\\', $repositories = $baseDir.'/Infra/Doctrine/Repository/*Repository.php')
    ;

    foreach (glob($repositories) as $file) {
        foreach ($reflector($repository = $ns.basename($file, '.php'))->getInterfaceNames() as $interface) {
            $services->alias($interface, $repository);
        }
    }
};
