<?php

declare(strict_types=1);

namespace MsgPhp\Eav\Infra\Doctrine\Type;

use MsgPhp\Domain\Infra\Doctrine\DomainIdType;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class AttributeValueIdType extends DomainIdType
{
    public const NAME = 'msgphp_attribute_value_id';
}
