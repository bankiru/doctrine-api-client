<?php

namespace Bankiru\Api\Doctrine\Exception;

class TypeException extends MappingException
{
    public static function cannotRedeclare($type)
    {
        return new self(sprintf('Cannot redeclare type "%s"', $type));
    }

    public static function unknown($type)
    {
        return new self(sprintf('Unknown type "%s"', $type));
    }
}
