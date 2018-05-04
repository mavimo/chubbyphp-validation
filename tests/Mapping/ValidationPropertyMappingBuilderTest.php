<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Validator\Mapping;

use Chubbyphp\Validation\Accessor\AccessorInterface;
use Chubbyphp\Validation\Constraint\ConstraintInterface;
use Chubbyphp\Validation\Mapping\ValidationPropertyMappingBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Chubbyphp\Validation\Mapping\ValidationPropertyMappingBuilder
 */
class ValidationPropertyMappingBuilderTest extends TestCase
{
    public function testGetDefaultMapping()
    {
        $propertyMapping = ValidationPropertyMappingBuilder::create('name', [])->getMapping();

        self::assertSame([], $propertyMapping->getConstraints());
        self::assertSame('name', $propertyMapping->getName());
        self::assertSame([], $propertyMapping->getGroups());
        self::assertInstanceOf(AccessorInterface::class, $propertyMapping->getAccessor());
    }

    public function testGetMapping()
    {
        $constraint = $this->getConstraint();

        $accessor = $this->getAccessor();

        $propertyMapping = ValidationPropertyMappingBuilder::create('name', [$constraint])
            ->setGroups(['group1'])
            ->setAccessor($accessor)
            ->getMapping();

        self::assertSame('name', $propertyMapping->getName());
        self::assertSame([$constraint], $propertyMapping->getConstraints());
        self::assertSame(['group1'], $propertyMapping->getGroups());
        self::assertSame($accessor, $propertyMapping->getAccessor());
    }

    /**
     * @return ConstraintInterface
     */
    private function getConstraint(): ConstraintInterface
    {
        /** @var ConstraintInterface|\PHPUnit_Framework_MockObject_MockObject $constraint */
        $constraint = $this->getMockBuilder(ConstraintInterface::class)->getMockForAbstractClass();

        return $constraint;
    }

    /**
     * @return AccessorInterface
     */
    private function getAccessor(): AccessorInterface
    {
        /** @var AccessorInterface|\PHPUnit_Framework_MockObject_MockObject $accessor */
        $accessor = $this->getMockBuilder(AccessorInterface::class)->getMockForAbstractClass();

        return $accessor;
    }
}
