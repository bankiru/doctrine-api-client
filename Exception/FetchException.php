<?php

namespace Bankiru\Api\Doctrine\Exception;

class FetchException extends HydrationException
{
    public static function expectedEntity(array $identifier)
    {
        return new static(
            sprintf(
                'Expected entity by identifier %s, but none found',
                json_encode($identifier)
            )
        );
    }
}
