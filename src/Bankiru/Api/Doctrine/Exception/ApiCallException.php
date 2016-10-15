<?php

namespace Bankiru\Api\Doctrine\Exception;

use ScayTrase\Api\Rpc\RpcResponseInterface;

class ApiCallException extends \RuntimeException implements DoctrineApiException
{
    public static function callFailed(RpcResponseInterface $response)
    {
        return new static(
            sprintf('RPC Call failed: %s', $response->getError())
        );
    }
}
