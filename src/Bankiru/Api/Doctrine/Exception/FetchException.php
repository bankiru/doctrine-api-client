<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 27.06.2016
 * Time: 13:52
 */

namespace Bankiru\Api\Doctrine\Exception;

class FetchException extends \RuntimeException implements DoctrineApiException
{
    public static function notFound()
    {
        return new self('Entity not found');
    }
}
