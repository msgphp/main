<?php

declare(strict_types=1);

namespace MsgPhp\User\Model;

use MsgPhp\Domain\DomainCollection;
use MsgPhp\Domain\GenericDomainCollection;
use MsgPhp\User\UserEmail;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait EmailsField
{
    /**
     * @var iterable|UserEmail[]
     */
    private $emails = [];

    /**
     * @return DomainCollection|UserEmail[]
     */
    public function getEmails(): DomainCollection
    {
        return new GenericDomainCollection($this->emails);
    }
}
