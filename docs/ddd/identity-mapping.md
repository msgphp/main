# Identity mapping

An identity mapping is a domain service and is bound to `MsgPhp\Domain\DomainIdentityMappingInterface`. It tells about
the identifier metadata for a known domain object.

## API

### `getIdentifierFieldNames(string $class): array`

Returns the identifier field names for `$class`. Any instance should have an identity composed of these field values.

---

### `getIdentity(object $object): array`

Returns the actual, non empty, identifier values of `$object`. Each identifier value is keyed by its corresponding
identifier field name.

## Implementations

### `MsgPhp\Domain\Infra\InMemory\DomainIdentityMapping`

Identity mapping based on a known in-memory mapping.

#### Basic example

```php
<?php

use MsgPhp\Domain\Infra\InMemory\DomainIdentityMapping;

// --- SETUP ---

class MyEntity
{
    public $id;
}

class MyCompositeEntity
{
    public $name;
    public $year;
}

$mapping = new DomainIdentityMapping([
    MyEntity::class => 'id',
    MyCompositeEntity::class => ['name', 'year'],
]);

// --- USAGE ---

$entity = new MyEntity();
$entity->id = ...;

$compositeEntity = new MyCompositeEntity();
$compositeEntity->name = ...;
$compositeEntity->year = ...;

$mapping->getIdentifierFieldNames(MyEntity::class); // ['id']
$mapping->getIdentifierFieldNames(MyCompositeEntity::class); // ['name', 'year']

$mapping->getIdentity($entity); // ['id' => ...]
$mapping->getIdentity($compositeEntity); // ['name' => ..., 'year' => ...]
```

### `MsgPhp\Domain\Infra\Doctrine\DomainIdentityMapping`

A Doctrine tailored identity mapping.

- [Read more](../infrastructure/doctrine-orm.md#domain-identity-mapping)
