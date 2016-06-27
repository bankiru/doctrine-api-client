<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 27.06.2016
 * Time: 9:36
 */

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

    public static function invalidClientName($class)
    {
        return new self(sprintf('Client name not specified for %s or any parent', $class));
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
}
