<?php

declare(strict_types=1);

namespace MsgPhp\User\Entity\Credential\Features;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait NicknameAsUsername
{
    /** @var string */
    private $nickname;

    public static function getUsernameField(): string
    {
        return 'nickname';
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getUsername(): string
    {
        return $this->nickname;
    }
}
