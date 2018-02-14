# Entity aware factory

An entity aware factory is a domain object factory, bound to `MsgPhp\Domain\Factory\EntityAwareFactoryInterface`.
Besides initializing any domain object via `create()` it's also capable to initialize an entity identifier / reference
object.

## Implementations

### `MsgPhp\Domain\Factory\EntityAwareFactory`

Generic entity factory and decorates any object factory. Additionally it must be provided with an entity to identifier
class mapping.

## API

### `create(string $class, array $context = []): object`

Inherited from `MsgPhp\Domain\Factory\DomainObjectFactoryInterface::create()`.

---

### `reference(string $class, $id): object`

Returns a reference for a known existing entity object.

---

### `identify(string $class, $value): DomainIdInterface`

Returns an identifier for the given entity class from a known primitive value.

---

### `nextIdentifier(string $class): DomainIdInterface`

Returns the next identifier for the given entity class. Depending on the implementation its value might be considered
empty if it's not capable to calculate one upfront.

## Generic example

```php
<?php

use MsgPhp\Domain\Factory\EntityAwareFactory;
use MsgPhp\Domain\DomainId;
use MsgPhp\Domain\Infra\Uuid\DomainId as DomainUuid;

$realFactory = ...;

$factory = new EntityAwareFactory($realFactory, [
    MyEntity::class => DomainId::class,
    MyOtherEntity::class => DomainUuid::class,
]);

/** @var DomainId $entityId */
$entityId = $factory->identify(MyEntity::class, '1');
$factory->nextIdentifier(MyEntity::class)->isEmpty(); // true

/** @var DomainUuid $entityId */
$entityId = $factory->identify(MyOtherEntity::class, 'cf3d2f85-6c86-44d1-8634-af51c91a9a74');
$factory->nextIdentifier(MyOtherEntity::class)->isEmpty(); // false
```

## Entity reference example

```php
<?php

use MsgPhp\Domain\Factory\EntityAwareFactory;

class MyCompositeEntity
{
    public function __construct($idA, $idB)
    { }
}

class Some
{
    public function __construct(MyCompositeEntity $entity)
    { }
}

$realFactory = ...;

$factory = new EntityAwareFactory($realFactory, [], function (string $class, $id) {
    if (MyCompositeEntity::class === $class && is_array($id)) {
        return new $class($id['idA'] ?? null, $id['idB'] ?? null);
    }

    return new $class($id);
});

/** @var Some $object */
$object = $factory->create(Some::class, [
    'entity' => $factory->reference(MyCompositeEntity::class, ['idA' => 1, 'idB' => 2]),
]);
```
