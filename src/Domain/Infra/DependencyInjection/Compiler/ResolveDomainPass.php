<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\DependencyInjection\Compiler;

use MsgPhp\Domain\Event\DomainEventInterface;
use MsgPhp\Domain\Message\DomainMessageBusInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class ResolveDomainPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $defaultEvents = array_values(array_filter(array_map(function (string $file) {
            return 'MsgPhp\\Domain\\Event\\'.basename($file, '.php');
        }, glob(dirname(dirname(dirname(__DIR__))).'/Event/*Event.php')), function (string $class): bool {
            return !is_subclass_of($class, DomainEventInterface::class);
        }));
        $container->setParameter($param = 'msgphp.domain.events', $container->hasParameter($param) ? array_merge($container->getParameter($param), $defaultEvents) : $defaultEvents);

        if (!$container->has(DomainMessageBusInterface::class)) {
            foreach ($container->findTaggedServiceIds('msgphp.domain.message_aware') as $id => $attr) {
                $container->removeDefinition($id);
            }
        }

        $classMapping = $container->getParameter('msgphp.domain.class_mapping');
        foreach ($container->findTaggedServiceIds('msgphp.domain.process_class_mapping') as $id => $attr) {
            $definition = $container->getDefinition($id);

            foreach ($attr as $attr) {
                if (!isset($attr['argument'])) {
                    continue;
                }

                $value = $definition->getArgument($attr['argument']);
                $definition->setArgument($attr['argument'], self::processClassMapping($value, $classMapping, !empty($attr['array_keys'])));
            }

            $definition->clearTag('msgphp.domain.process_class_mapping');
        }
    }

    private static function processClassMapping($value, array $classMapping, bool $arrayKeys = false)
    {
        if (is_string($value) && isset($classMapping[$value])) {
            return $classMapping[$value];
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $k => $v) {
            $v = self::processClassMapping($v, $classMapping, $arrayKeys);
            if ($arrayKeys) {
                $k = self::processClassMapping($k, $classMapping);
            }

            $result[$k] = $v;
        }

        return $result;
    }
}
