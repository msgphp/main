# Domain events

A domain event is bound to `MsgPhp\Domain\Event\DomainEventInterface`. Its purpose is to identify concrete domain events
and represents something that happens, leading to an application state change.

## Implementations

Domain events provided and handled by default [entity features](../ddd/entities.md):

- `MsgPhp\Domain\Event\ConfirmDomainEvent`
- `MsgPhp\Domain\Event\DisableDomainEvent`
- `MsgPhp\Domain\Event\EnableDomainEvent`
