<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Tests\Entity\Fields;

use MsgPhp\Domain\Entity\Fields\EnabledField;
use PHPUnit\Framework\TestCase;

final class EnabledFieldTest extends TestCase
{
    public function testField(): void
    {
        $this->assertFalse($this->getObject()->isEnabled());
        $this->assertTrue($this->getObject(true)->isEnabled());
        $this->assertFalse($this->getObject(false)->isEnabled());
    }

    private function getObject($value = null)
    {
        if (func_num_args()) {
            return new class($value) {
                use EnabledField;

                public function __construct($value)
                {
                    $this->enabled = $value;
                }
            };
        }

        return new class() {
            use EnabledField;
        };
    }
}
