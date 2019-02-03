<?php

declare(strict_types=1);

namespace MsgPhp\Eav\Entity;

use MsgPhp\Eav\AttributeValueIdInterface;
use MsgPhp\Eav\Entity\Fields\AttributeField;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
abstract class AttributeValue
{
    use AttributeField;

    private $boolValue;
    private $intValue;
    private $floatValue;
    private $stringValue;
    private $dateTimeValue;
    private $checksum;
    private $value;
    private $isNull;

    public static function getChecksum($value): string
    {
        return md5(serialize([\gettype($value), static::prepareChecksumValue($value)]));
    }

    public function __construct(Attribute $attribute, $value)
    {
        $this->attribute = $attribute;

        $this->changeValue($value);
    }

    abstract public function getId(): AttributeValueIdInterface;

    final public function getValue()
    {
        if ($this->isNull) {
            return null;
        }

        if (null !== $this->value) {
            return $this->value;
        }

        if (null === $value = $this->doGetValue()) {
            $this->isNull = true;
        }

        return $this->value = $value;
    }

    final public function changeValue($value): void
    {
        $this->doClearValue();
        $this->isNull = null === $value;

        if (!$this->isNull) {
            $this->doSetValue($value);
        }

        $this->value = $value;
        $this->checksum = static::getChecksum($value);
    }

    protected static function prepareChecksumValue($value)
    {
        return $value;
    }

    protected function doClearValue(): void
    {
        $this->boolValue = $this->intValue = $this->floatValue = $this->stringValue = $this->dateTimeValue = null;
    }

    protected function doSetValue($value): void
    {
        if (\is_bool($value)) {
            $this->boolValue = $value;
        } elseif (\is_int($value)) {
            $this->intValue = $value;
        } elseif (\is_float($value)) {
            $this->floatValue = $value;
        } elseif (\is_string($value)) {
            $this->stringValue = $value;
        } elseif ($value instanceof \DateTimeInterface) {
            $this->dateTimeValue = $value;
        } else {
            throw new \LogicException(sprintf('Unsupported attribute value type "%s".', \gettype($value)));
        }
    }

    protected function doGetValue()
    {
        return $this->boolValue ?? $this->intValue ?? $this->floatValue ?? $this->stringValue ?? $this->dateTimeValue;
    }
}
