<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Tests\Event;

use MsgPhp\Domain\Event\{DomainEventHandlerInterface, DomainEventHandlerTrait, DomainEventInterface};
use MsgPhp\Domain\Exception\UnknownDomainEventException;
use PHPUnit\Framework\TestCase;

final class DomainEventHandlerTraitTest extends TestCase
{
    public function testHandleEvent(): void
    {
        $object = $this->getObject();

        $this->assertTrue($object->handleEvent($event = new TestEvent()));
        $this->assertTrue($event->handled);
        $this->assertTrue($object->handleEvent($event = new TestEventDifferentSuffix()));
        $this->assertTrue($event->handled);

        $event = $this->getMockBuilder(DomainEventInterface::class)
            ->setMockClassName('MsgPhp_Test_Root_Event')
            ->getMock();
        $this->assertFalse($object->handleEvent($event));
    }

    public function testHandleEventWithUnknownEvent(): void
    {
        $object = $this->getObject();

        $this->expectException(UnknownDomainEventException::class);

        $object->handleEvent($this->createMock(DomainEventInterface::class));
    }

    private function getObject()
    {
        return new class() implements DomainEventHandlerInterface {
            use DomainEventHandlerTrait;

            private function handleTestEvent(TestEvent $event): bool
            {
                $event->handled = true;

                return true;
            }

            private function handleTestEventDifferentSuffixEvent(TestEventDifferentSuffix $event): bool
            {
                $event->handled = true;

                return true;
            }

            private function handleMsgPhp_Test_Root_Event(DomainEventInterface $event): bool
            {
                return false;
            }
        };
    }
}

class TestEvent implements DomainEventInterface
{
    public $handled = false;
}

class TestEventDifferentSuffix extends TestEvent
{
}
