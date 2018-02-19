<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Tests\Factory;

use MsgPhp\Domain\Exception\InvalidClassException;
use MsgPhp\Domain\Factory\{DomainObjectFactory, DomainObjectFactoryInterface};
use PHPUnit\Framework\TestCase;

final class DomainObjectFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $object = (new DomainObjectFactory())->create(TestObject::class, ['arg_a' => 1, 'arg_b' => 'foo', 'bar' => 'baz']);

        $this->assertInstanceOf(TestObject::class, $object);
        $this->assertSame(1, $object->a);
        $this->assertSame('foo', $object->b);
    }

    public function testCreateWithUnknownObject(): void
    {
        $factory = new DomainObjectFactory();

        $this->expectException(InvalidClassException::class);

        $factory->create(__NAMESPACE__.'\\UnknownTestObject');
    }

    public function testCreateWithUnknownNestedObject(): void
    {
        $factory = new DomainObjectFactory();

        $this->assertInstanceOf(KnownTestObject::class, $factory->create(KnownTestObject::class));
        $this->assertInstanceOf(KnownTestObject::class, $factory->create(KnownTestObject::class, ['unknown' => 'foo']));

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageRegExp(sprintf('/^Argument 1 passed to %s::__construct\(\) must be an instance of MsgPhp\\\Domain\\\Tests\\\Factory\\\UnknownTestObject or null, integer given\b/', preg_quote(KnownTestObject::class)));

        $factory->create(KnownTestObject::class, [123]);
    }

    public function testCreateWithNamedContext(): void
    {
        $object = (new DomainObjectFactory())->create(TestObject::class, [1 => 'b', 'arg_a' => 'ignore', 0 => 'ignore', 'argA' => 'a']);

        $this->assertSame('a', $object->a);
        $this->assertSame('b', $object->b);
    }

    public function testCreateWithNumericContext(): void
    {
        $object = (new DomainObjectFactory())->create(TestObject::class, [1 => 'b', 'arg_a' => 'a', 0 => 'ignore']);

        $this->assertSame('a', $object->a);
        $this->assertSame('b', $object->b);
    }

    public function testCreateWithoutConstructor(): void
    {
        $this->assertInstanceOf(EmptyTestObject::class, (new DomainObjectFactory())->create(EmptyTestObject::class, ['arg']));
    }

    public function testCreateWithPrivateConstructor(): void
    {
        $factory = new DomainObjectFactory();

        $this->expectException(\Error::class);

        $factory->create(PrivateTestObject::class, ['arg']);
    }

    public function testNestedCreate(): void
    {
        $object = (new DomainObjectFactory())->create(NestedTestObject::class, [
            'test' => ['arg_a' => 'nested_a', 'arg_b' => 'nested_b'],
            'self' => ['test' => ['arg_a' => 'foo', 'arg_b' => 'bar'], 'other' => $other = new TestObject(1, 2)],
        ]);

        $this->assertInstanceOf(NestedTestObject::class, $object);
        $this->assertInstanceOf(TestObject::class, $object->test);
        $this->assertSame('nested_a', $object->test->a);
        $this->assertSame('nested_b', $object->test->b);
        $this->assertInstanceOf(NestedTestObject::class, $object->self);
        $this->assertSame('foo', $object->self->test->a);
        $this->assertSame('bar', $object->self->test->b);
        $this->assertSame($other, $object->self->other);
    }

    public function testNestedCreateWithoutContext(): void
    {
        $factory = new DomainObjectFactory();

        $this->expectException(\LogicException::class);

        $factory->create(NestedTestObject::class);
    }

    public function testNestedCreateWithNestedFactory(): void
    {
        $nestedFactory = $this->createMock(DomainObjectFactoryInterface::class);
        $nestedFactory->expects($this->any())
            ->method('create')
            ->willReturn($nested = new TestObject(1, 2));

        $factory = new DomainObjectFactory();
        $factory->setNestedFactory($nestedFactory);

        $this->assertInstanceOf(NestedTestObject::class, $object = $factory->create(NestedTestObject::class, [['a', 'b']]));
        $this->assertSame($nested, $object->test);
    }

    public function testNestedCreateWithInvalidNestedFactory(): void
    {
        $nestedFactory = $this->createMock(DomainObjectFactoryInterface::class);
        $nestedFactory->expects($this->any())
            ->method('create')
            ->willThrowException(InvalidClassException::create(TestObject::class));

        $factory = new DomainObjectFactory();
        $factory->setNestedFactory($nestedFactory);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageRegExp(sprintf('/^Argument 1 passed to %s::__construct\(\) must be an instance of MsgPhp\\\Domain\\\Tests\\\Factory\\\TestObject, array given\b/', preg_quote(NestedTestObject::class)));

        $factory->create(NestedTestObject::class, [['a', 'b']]);
    }
}

class TestObject
{
    public $a;
    public $b;

    public function __construct($argA, $argB)
    {
        $this->a = $argA;
        $this->b = $argB;
    }
}

class NestedTestObject
{
    public $test;
    public $self;
    public $other;

    public function __construct(TestObject $test, ?self $self, ?TestObject $other)
    {
        $this->test = $test;
        $this->self = $self;
        $this->other = $other;
    }
}

class EmptyTestObject
{
}

class PrivateTestObject
{
    private function __construct()
    {
    }
}

class KnownTestObject
{
    public function __construct(UnknownTestObject $arg = null)
    {
    }
}
