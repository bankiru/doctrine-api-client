<?php

namespace Bankiru\Api\Doctrine\Exception;

use Doctrine\Common\Persistence\Mapping\MappingException as BaseMappingException;

class MappingException extends BaseMappingException implements DoctrineApiException
{
    public static function unknownAlias($alias)
    {
        return new self(sprintf('Unknown namespace alias "%s"', $alias));
    }

    public static function noSuchProperty($property, $class)
    {
        return new self(
            'Property "%s" not present within class %s',
            $property,
            $class
        );
    }

    public static function noClientSpecified($class)
    {
        return new self(sprintf('Client not specified for %s or any parent', $class));
    }

    public static function invalidClientSpecified($name, $message)
    {
        return new self(sprintf('Could not resolve client "%s": %s', $name, $message));
    }

    public static function noApiSpecified($class)
    {
        return new self(sprintf('API factory not specified for %s or any parent', $class));
    }

    public static function invalidApiSpecified($name, $message)
    {
        return new self(sprintf('Could not resolve API factory "%s": %s', $name, $message));
    }

    public static function unknownField($field, $class)
    {
        return new self(sprintf('No mapping for field "%s" in %s', $field, $class));
    }

    public static function unknownAssociation($field, $class)
    {
        return new self(sprintf('No mapping for association "%s" in %s', $field, $class));
    }

    public static function invalidIdentifierStructure()
    {
        return new self('Identifier structure does not match mapping');
    }

    public static function noMethods()
    {
        return new self('No methods or entity-path configured');
    }

    public static function unknownApiFactory($alias)
    {
        return new static(sprintf('Unknown factory to create API: %s', $alias));
    }

    public static function nameIsMandatoryForDiscriminatorColumns($alias)
    {
        return new static(sprintf('Name is mandatory for discriminator column: %s', $alias));
    }

    public static function duplicateColumnName($alias, $column)
    {
        return new static(sprintf('Duplicate column name "%s": %s', $column, $alias));
    }

    public static function invalidDiscriminatorColumnType($alias, $type)
    {
        return new static(sprintf('Invalud discriminator column type "%s": %s', $type, $alias));
    }

    public static function mappedClassNotPartOfDiscriminatorMap($name, $rootEntityName)
    {
        return new static(sprintf('Mapped class "%s" is not a part of discriminator map: %s', $name, $rootEntityName));
    }

    public static function unknownDiscriminatorValue($value, $alias)
    {
        return new static(sprintf('Unknown discriminator value "%s": %s', $value, $alias));
    }

    public static function duplicateDiscriminatorEntry($name, array $duplicates, array $map)
    {
        return new static(
            sprintf('Discriminator map contains duplicate values "%s": %s', implode('", "', $duplicates), $name)
        );
    }

    public static function invalidClassInDiscriminatorMap($className, $name)
    {
        return new static(
            sprintf('Invalid class "%s" for discriminator map: %s', $className, $name)
        );
    }
}
