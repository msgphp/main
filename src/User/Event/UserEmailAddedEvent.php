<?php

declare(strict_types=1);

namespace MsgPhp\User\Event;

use MsgPhp\User\Entity\UserEmail;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class UserEmailAddedEvent
{
    /**
     * @var UserEmail
     */
    public $userEmail;

    /**
     * @var array
     */
    public $context;

    final public function __construct(UserEmail $userEmail, array $context)
    {
        $this->userEmail = $userEmail;
        $this->context = $context;
    }
}
