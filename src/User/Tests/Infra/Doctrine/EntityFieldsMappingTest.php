<?php

declare(strict_types=1);

namespace MsgPhp\User\Tests\Infra\Doctrine;

use MsgPhp\User\Entity\Features;
use MsgPhp\User\Infra\Doctrine\EntityFieldsMapping;
use PHPUnit\Framework\TestCase;

final class EntityFieldsMappingTest extends TestCase
{
    public function testMapping(): void
    {
        $available = array_flip(array_map(function (string $file): string {
            return 'MsgPhp\\User\\Entity\\'.basename(dirname($file)).'\\'.basename($file, '.php');
        }, glob(dirname(dirname(dirname(__DIR__))).'/Entity/{Features,Fields}/*.php', \GLOB_BRACE)));
        unset(
            $available[Features\AbstractCredential::class],
            $available[Features\AbstractPasswordCredential::class],
            $available[Features\AbstractSaltedPasswordCredential::class]
        );

        $mapping = array_keys(EntityFieldsMapping::getObjectFieldMapping());
        sort($mapping);

        $this->assertSame(array_keys($available), $mapping);
    }
}
