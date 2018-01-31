<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\Console\ContextBuilder;

use MsgPhp\Domain\{DomainCollectionInterface, DomainIdInterface};
use MsgPhp\Domain\Factory\ClassMethodResolver;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class ClassContextBuilder implements ContextBuilderInterface
{
    private $class;
    private $method;
    private $elementProviders;
    private $classMapping;
    private $resolved;
    private $isOption = [];

    /**
     * @param ContextElementProviderInterface[] $elementProviders
     */
    public function __construct(string $class, string $method, iterable $elementProviders = [], array $classMapping = [])
    {
        $this->class = $class;
        $this->method = $method;
        $this->elementProviders = $elementProviders;
        $this->classMapping = $classMapping;
    }

    public function configure(InputDefinition $definition): void
    {
        foreach ($this->resolve() as $argument) {
            $this->isOption[$field = $argument['field']] = true;
            if ('bool' === $argument['type']) {
                $mode = InputOption::VALUE_NONE;
            } elseif (self::isComplex($argument['type'])) {
                $mode = InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY;
            } elseif (!$argument['required']) {
                $mode = InputOption::VALUE_OPTIONAL;
            } else {
                $mode = InputArgument::OPTIONAL;
                $this->isOption[$field] = false;
            }

            if ($this->isOption[$field]) {
                $definition->addOption(new InputOption($field, null, $mode, $argument['element']->description));
            } else {
                $definition->addArgument(new InputArgument($field, $mode, $argument['element']->description));
            }
        }
    }

    public function getContext(InputInterface $input, StyleInterface $io, iterable $resolved = null): array
    {
        $context = [];
        $interactive = $input->isInteractive();

        foreach ($resolved ?? $this->resolve() as $argument) {
            $key = $argument['name'];
            $field = $argument['field'] ?? $key;
            $value = null === $resolved
                ? ($this->isOption[$field] ? $input->getOption($field) : $input->getArgument($field))
                : ($argument['value'] ?? null);

            if (null !== $value && false !== $value && [] !== $value) {
                $context[$key] = $value;
                continue;
            }

            if (!$argument['required']) {
                $context[$key] = $argument['default'];
                continue;
            }

            if ([] === $value && self::isObject($type = $argument['type'])) {
                $class = $this->classMapping[$type] ?? $type;
                $method = is_subclass_of($class, DomainCollectionInterface::class) || is_subclass_of($class, DomainIdInterface::class) ? 'fromValue' : '__construct';
                $context[$key] = $this->getContext($input, $io, array_map(function (array $nestedArgument) use ($class, $method, $argument): array {
                    if ('bool' === $nestedArgument['type']) {
                        $nestedArgument['value'] = false;
                    } elseif (self::isComplex($argument['type'])) {
                        $nestedArgument['value'] = [];
                    }

                    $element = $this->getElement($class, $method, $nestedArgument['name']);
                    $element->label = $argument['element']->label.' > '.$element->label;

                    return ['element' => $element] + $nestedArgument;
                }, ClassMethodResolver::resolve($class, $method)));
                continue;
            }

            if (!$interactive) {
                throw new \LogicException(sprintf('No value provided for "%s".', $field));
            }

            $context[$key] = $this->askRequiredValue($io, $argument['element'], $value);
        }

        return $context;
    }

    private static function isComplex(?string $type): bool
    {
        return 'array' === $type || 'iterable' === $type || self::isObject($type);
    }

    private static function isObject(?string $type): bool
    {
        return null !== $type && (class_exists($type) || interface_exists($type));
    }

    private function resolve(): iterable
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $this->resolved = [];

        foreach (ClassMethodResolver::resolve($this->class, $this->method) as $argument) {
            $field = $argument['key'];
            $i = 0;
            while (isset($this->resolved[$field])) {
                $field = $argument['key'].++$i;
            }

            $this->resolved[$field] = ['field' => $field, 'element' => $this->getElement($this->class, $this->method, $argument['name'])] + $argument;
        }

        return $this->resolved;
    }

    private function getElement(string $class, string $method, string $argument): ContextElement
    {
        foreach ($this->elementProviders as $provider) {
            if (null !== $element = $provider->getElement($class, $method, $argument)) {
                return $element;
            }
        }

        return new ContextElement(str_replace('_', ' ', ucfirst($argument)));
    }

    private function askRequiredValue(StyleInterface $io, ContextElement $element, $default)
    {
        if (null === $default) {
            do {
                $value = $io->ask($element->label);
            } while (null === $value);

            return $value;
        }

        if (false === $default) {
            return $io->confirm($element->label, false);
        }

        if ([] === $default) {
            $i = 1;
            $value = [];
            do {
                $value[] = $io->ask($element->label.(1 < $i ? ' ('.$i.')' : ''));
                ++$i;
            } while ($io->confirm('Add another value?', false));

            return $value;
        }

        return $default;
    }
}
