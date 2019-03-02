<?php

declare(strict_types=1);

namespace MsgPhp\Domain;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait DomainIdTrait
{
    /**
     * @var string|null
     */
    private $id;

    /**
     * @return static
     */
    public static function fromValue($value): DomainIdInterface
    {
        if (null !== $value && !\is_string($value)) {
            $value = (string) $value;
        }

        return new static($value);
    }

    public function __construct(string $id = null)
    {
        if ('' === $id) {
            throw new \LogicException('A domain ID cannot be empty.');
        }

        $this->id = $id;
    }

    public function isEmpty(): bool
    {
        return null === $this->id;
    }

    public function equals(DomainIdInterface $id): bool
    {
        if ($id === $this) {
            return true;
        }

        if (null === $this->id || $id->isEmpty() || static::class !== \get_class($id)) {
            return false;
        }

        return $this->id === $id->toString();
    }

    public function toString(): string
    {
        return $this->id ?? '';
    }

    public function __toString(): string
    {
        return $this->id ?? '';
    }
}