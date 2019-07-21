<?php

declare(strict_types=1);

namespace MsgPhp\Eav\Command\Handler;

use MsgPhp\Domain\DomainMessageBus;
use MsgPhp\Domain\Factory\DomainObjectFactory;
use MsgPhp\Eav\Attribute;
use MsgPhp\Eav\AttributeId;
use MsgPhp\Eav\Command\CreateAttribute;
use MsgPhp\Eav\Event\AttributeCreated;
use MsgPhp\Eav\Repository\AttributeRepository;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class CreateAttributeHandler
{
    private $factory;
    private $bus;
    private $repository;

    public function __construct(DomainObjectFactory $factory, DomainMessageBus $bus, AttributeRepository $repository)
    {
        $this->factory = $factory;
        $this->bus = $bus;
        $this->repository = $repository;
    }

    public function __invoke(CreateAttribute $command): void
    {
        $context = $command->context;
        $context['id'] = $context['id'] ?? $this->factory->create(AttributeId::class);
        $attribute = $this->factory->create(Attribute::class, $context);

        $this->repository->save($attribute);
        $this->bus->dispatch($this->factory->create(AttributeCreated::class, compact('attribute', 'context')));
    }
}
