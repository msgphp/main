<?php

declare(strict_types=1);

namespace MsgPhp\User\Entity\Features;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait ResettablePassword
{
    /** @var string|null */
    private $passwordResetToken;

    /** @var \DateTimeInterface|null */
    private $passwordRequestedAt;

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function getPasswordRequestedAt(): ?\DateTimeInterface
    {
        return $this->passwordRequestedAt;
    }

    public function requestPassword(string $token = null): void
    {
        $this->passwordResetToken = $token ?? bin2hex(random_bytes(32));
        $this->passwordRequestedAt = new \DateTimeImmutable();
    }
}
