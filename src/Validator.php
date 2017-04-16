<?php

declare(strict_types=1);

namespace Chubbyphp\Validation;

use Chubbyphp\Validation\Error\ErrorInterface;
use Chubbyphp\Validation\Mapping\ObjectMappingInterface;
use Chubbyphp\Validation\Registry\ObjectMappingRegistry;

final class Validator implements ValidatorInterface
{
    /**
     * @var ObjectMappingRegistry
     */
    private $objectMappingRegistry;

    /**
     * @param ObjectMappingRegistry $objectMappingRegistry
     */
    public function __construct(ObjectMappingRegistry $objectMappingRegistry)
    {
        $this->objectMappingRegistry = $objectMappingRegistry;
    }

    /**
     * @param object $object
     * @param string $path
     * @return string[]
     */
    public function validateObject($object, string $path = ''): array
    {
        $class = get_class($object);

        $objectMapping = $this->objectMappingRegistry->getObjectMappingForClass($class);

        return array_merge(
            $this->propertyConstraints($objectMapping, $object, $path),
            $this->objectConstraints($objectMapping, $object, $path)
        );
    }

    /**
     * @param ObjectMappingInterface $objectMapping
     * @param $object
     * @param string $path
     * @return ErrorInterface[]
     */
    private function propertyConstraints(ObjectMappingInterface $objectMapping, $object, string $path)
    {
        $errors = [];
        foreach ($objectMapping->getPropertyMappings() as $propertyMapping) {
            $property = $propertyMapping->getName();

            $propertyReflection = new \ReflectionProperty($object, $property);
            $propertyReflection->setAccessible(true);

            $propertyValue = $propertyReflection->getValue($object);

            $subPath = '' !== $path ? $path . '.' . $property : $property;

            foreach ($propertyMapping->getConstraints() as $constraint) {
                $errors = array_merge($errors, $constraint->validate($this, $subPath, $propertyValue));
            }
        }

        return $errors;
    }

    /**
     * @param ObjectMappingInterface $objectMapping
     * @param $object
     * @param string $path
     * @return ErrorInterface[]
     */
    private function objectConstraints(ObjectMappingInterface $objectMapping, $object, string $path): array
    {
        $errors = [];
        foreach ($objectMapping->getConstraints() as $constraint) {
            $errors = array_merge($errors, $constraint->validate($this, $path, $object));
        }

        return $errors;
    }
}
