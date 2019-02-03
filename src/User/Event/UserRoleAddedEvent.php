<?php

declare(strict_types=1);

namespace MsgPhp\User\Event;

use MsgPhp\User\Entity\UserRole;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class UserRoleAddedEvent
{
    /**
     * @var UserRole
     */
    public $userRole;

    /**
     * @var array
     */
    public $context;

    final public function __construct(UserRole $userRole, array $context)
    {
        $this->userRole = $userRole;
        $this->context = $context;
    }
}
