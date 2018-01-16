<?php

declare(strict_types=1);

namespace MsgPhp\User\Entity;

use MsgPhp\Eav\Entity\AttributeValue;
use MsgPhp\Eav\Entity\Fields\AttributeValueField;
use MsgPhp\User\Entity\Fields\UserField;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class UserAttributeValue
{
    use UserField;
    use AttributeValueField;

    public function __construct(User $user, AttributeValue $attributeValue)
    {
        $this->user = $user;
        $this->attributeValue = $attributeValue;
    }
}
