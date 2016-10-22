<?php

namespace Bankiru\Api\Doctrine\Exception;

class ApiFactoryRegistryException extends \Exception implements DoctrineApiException
{
    public static function unknown($alias)
    {
        return new static(sprintf('Unknown API alias to create: '.$alias));
    }
}
