<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\Uuid;

use MsgPhp\Domain\DomainIdInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait DomainIdTrait
{
    /**
     * @var UuidInterface
     */
    private $uuid;

    /**
     * @return static
     */
    final public static function fromValue($value): DomainIdInterface
    {
        if (null !== $value && !$value instanceof UuidInterface) {
            $value = Uuid::fromString((string) $value);
        }

        return new static($value);
    }

    final public function __construct(UuidInterface $uuid = null)
    {
        $this->uuid = $uuid ?? Uuid::uuid4();
    }

    final public function isEmpty(): bool
    {
        return false;
    }

    final public function equals(DomainIdInterface $id): bool
    {
        if ($id === $this) {
            return true;
        }

        if (static::class !== \get_class($id)) {
            return false;
        }

        return $this->uuid->equals(Uuid::fromString($id->toString()));
    }

    final public function toString(): string
    {
        return $this->uuid->toString();
    }

    final public function __toString(): string
    {
        return $this->uuid->toString();
    }
}