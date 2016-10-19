<?php

namespace Bankiru\Api\Doctrine\Exception;

class FetchException extends \RuntimeException implements DoctrineApiException
{
    public static function notFound()
    {
        return new self('Entity not found');
    }
}
