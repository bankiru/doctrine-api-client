<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 27.06.2016
 * Time: 13:54
 */

namespace Bankiru\Api\Doctrine\Exception;

class TypeException extends \LogicException implements DoctrineApiException
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
