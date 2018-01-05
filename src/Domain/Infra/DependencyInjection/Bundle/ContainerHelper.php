<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\DependencyInjection\Bundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Events as DoctrineOrmEvents;
use MsgPhp\Domain\Entity\{ChainEntityFactory, ClassMappingEntityFactory, EntityFactoryInterface};
use MsgPhp\Domain\Infra\Doctrine\Mapping\{EntityFields, ObjectFieldMappingListener};
use MsgPhp\Domain\Infra\SimpleBus\{DomainCommandBus, DomainEventBus};
use SimpleBus\SymfonyBridge\{SimpleBusCommandBusBundle, SimpleBusEventBusBundle};
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class ContainerHelper
{
    public static function getBundles(ContainerBuilder $container): array
    {
        // @todo remove eventually
        return array_flip($container->getParameter('kernel.bundles'));
    }

    public static function hasBundle(ContainerBuilder $container, string $class): bool
    {
        return in_array($class, $container->getParameter('kernel.bundles'), true);
    }

    public static function getClassReflector(ContainerBuilder $container): \Closure
    {
        return function (string $class) use ($container): \ReflectionClass {
            return self::getClassReflection($container, $class);
        };
    }

    public static function getClassReflection(ContainerBuilder $container, ?string $class): \ReflectionClass
    {
        if (!$class || !($reflection = $container->getReflectionClass($class))) {
            throw new InvalidArgumentException(sprintf('Invalid class "%s".', $class));
        }

        return $reflection;
    }

    public static function addCompilerPassOnce(ContainerBuilder $container, string $class, callable $initializer = null, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): void
    {
        $passes = array_flip(array_map(function (CompilerPassInterface $pass): string {
            return get_class($pass);
        }, $container->getCompiler()->getPassConfig()->getPasses()));

        if (!isset($passes[$class])) {
            $container->addCompilerPass(null === $initializer ? new $class() : $initializer(), $type, $priority);
        }
    }

    public static function configureEntityFactory(ContainerBuilder $container, array $mapping, array $idMapping): void
    {
        if (!$container->hasDefinition('msgphp.entity_factory')) {
            $container->register('msgphp.entity_factory', ChainEntityFactory::class)
                ->setPublic(false)
                ->setArgument('$factories', new TaggedIteratorArgument('msgphp.entity_factory'));
        }

        if (!$container->has(EntityFactoryInterface::class)) {
            $container->setAlias(EntityFactoryInterface::class, new Alias('msgphp.entity_factory', false));
        }

        $container->register('msgphp.entity_factory.'.md5(uniqid()), ClassMappingEntityFactory::class)
            ->setPublic(false)
            ->setArgument('$mapping', $mapping)
            ->setArgument('$idMapping', $idMapping)
            ->setArgument('$factory', new Reference('msgphp.entity_factory'))
            ->addTag('msgphp.entity_factory');
    }

    public static function configureDoctrine(ContainerBuilder $container, array $ormObjectFieldMappings = []): void
    {
        if (!self::hasBundle($container, DoctrineBundle::class)) {
            return;
        }

        if (class_exists(DoctrineOrmEvents::class)) {
            if (!$container->has(ObjectFieldMappingListener::class)) {
                $container->register(ObjectFieldMappingListener::class)
                    ->setPublic(false)
                    ->addTag('doctrine.event_listener', ['event' => DoctrineOrmEvents::loadClassMetadata]);
            }

            array_unshift($ormObjectFieldMappings, EntityFields::class);

            foreach ($ormObjectFieldMappings as $class) {
                if (!$container->has($class)) {
                    $container->register($class)
                        ->setPublic(false)
                        ->addTag('msgphp.doctrine.object_field_mapping');
                }
            }
        }
    }

    public static function configureSimpleBus(ContainerBuilder $container, string $type = null): void
    {
        $buses = [
            'command_bus' => [SimpleBusCommandBusBundle::class, DomainCommandBus::class],
            'event_bus' => [SimpleBusEventBusBundle::class, DomainEventBus::class],
        ];

        if (null === $type) {
            foreach (array_keys($buses) as $type) {
                self::configureSimpleBus($container, $type);
            }

            return;
        }

        if (!isset($buses[$type])) {
            throw new InvalidArgumentException(sprintf('Invalid message bus type "%s".', $type));
        }

        list($bundle, $class) = $buses[$type];

        if (!self::hasBundle($container, $bundle) || $container->has($class)) {
            return;
        }

        $container->register($class)
            ->setPublic(false)
            ->addArgument(new Reference($type));

        foreach (self::getClassReflection($container, $class)->getInterfaceNames() as $interface) {
            if (!$container->has($interface)) {
                $container->setAlias($interface, new Alias($class, false));
            }
        }
    }

    private function __construct()
    {
    }
}
