<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder as BaseNodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Builder\ParentNodeDefinitionInterface;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\NodeInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class ClassMappingNodeDefinition extends VariableNodeDefinition implements ParentNodeDefinitionInterface
{
    public const NAME = 'class_mapping';

    /** @var BaseNodeBuilder|null */
    private $builder;
    private $prototype;

    public function requireClasses(array $classes): self
    {
        foreach ($classes as $class) {
            $this->validate()
                ->ifTrue(function (array $value) use ($class): bool {
                    return !isset($value[$class]);
                })
                ->thenInvalid(sprintf('Class mapping for "%s" must be configured.', $class));
        }

        if ($classes) {
            $this->isRequired();
        }

        return $this;
    }

    public function disallowClasses(array $classes): self
    {
        foreach ($classes as $class) {
            $this->validate()
                ->ifTrue(function (array $value) use ($class): bool {
                    return isset($value[$class]);
                })
                ->thenInvalid(sprintf('Class mapping for "%s" is not applicable.', $class));
        }

        return $this;
    }

    public function forceSubClassValues(): self
    {
        $this->validate()->always(function (array $value): array {
            foreach ($value as $class => $mappedClass) {
                if (!is_subclass_of($mappedClass, $class)) {
                    throw new \LogicException(sprintf('Class "%s" must be a sub class of "%s".', $mappedClass, $class));
                }
            }

            return $value;
        });

        return $this;
    }

    public function children(): BaseNodeBuilder
    {
        throw new \BadMethodCallException(sprintf('Method "%s" is not applicable.', __METHOD__));
    }

    public function append(NodeDefinition $node): self
    {
        throw new \BadMethodCallException(sprintf('Method "%s" is not applicable.', __METHOD__));
    }

    public function getChildNodeDefinitions(): array
    {
        throw new \BadMethodCallException(sprintf('Method "%s" is not applicable.', __METHOD__));
    }

    public function setBuilder(BaseNodeBuilder $builder): void
    {
        $this->builder = $builder;
    }

    /**
     * @return NodeParentInterface|BaseNodeBuilder|NodeDefinition|ArrayNodeDefinition|VariableNodeDefinition|NodeBuilder|null
     */
    public function end()
    {
        return $this->parent;
    }

    protected function instantiateNode(): ClassMappingNode
    {
        return new ClassMappingNode($this->name, $this->parent, $this->pathSeparator ?? '.');
    }

    protected function createNode(): NodeInterface
    {
        /** @var ClassMappingNode $node */
        $node = parent::createNode();
        $node->setKeyAttribute('class');

        $prototype = $this->getPrototype();
        $prototype->parent = $node;
        $node->setPrototype($prototype->getNode());

        return $node;
    }

    private function getPrototype(): NodeDefinition
    {
        if (null === $this->prototype) {
            $this->prototype = ($this->builder ?? new NodeBuilder())->node(null, 'scalar');
            $this->prototype->setParent($this);
            $this->prototype->cannotBeEmpty();
            $this->prototype->validate()
                ->ifTrue(function ($value): bool {
                    return !is_string($value) || (!class_exists($value) && !interface_exists($value));
                })
                ->thenInvalid('Mapped class %s does not exists.');
        }

        return $this->prototype;
    }
}
