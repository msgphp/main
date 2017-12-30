<?php

declare(strict_types=1);

namespace MsgPhp\User\Entity\Features;

use MsgPhp\Domain\Entity\Features\AbstractUpdated;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait ResettablePassword
{
    use AbstractUpdated;

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

        $this->onUpdate();
    }
}
