<?php

declare(strict_types=1);

namespace MsgPhp\Eav\Entity\Fields;

use MsgPhp\Domain\DomainCollectionInterface;
use MsgPhp\Domain\Factory\DomainCollectionFactory;
use MsgPhp\Eav\Entity\{Attribute, AttributeValue};

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait AttributesField
{
    /**
     * @return DomainCollectionInterface|Attribute[]
     */
    public function getAttributes(): DomainCollectionInterface
    {
        $attributes = [];

        foreach ($this->getAttributeValues() as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            if (isset($attributes[$attributeId = $attribute->getId()->toString()])) {
                continue;
            }

            $attributes[$attributeId] = $attribute;
        }

        return DomainCollectionFactory::create(array_values($attributes));
    }

    /**
     * @return DomainCollectionInterface|AttributeValue[]
     */
    abstract public function getAttributeValues(): DomainCollectionInterface;
}
