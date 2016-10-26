<?php

namespace Bankiru\Api\Doctrine\Exception;

use ScayTrase\Api\Rpc\Exception\RemoteCallFailedException;
use ScayTrase\Api\Rpc\RpcErrorInterface;

class ApiCallException extends RemoteCallFailedException implements DoctrineApiException
{
    public static function callFailed(RpcErrorInterface $error)
    {
        return new static(sprintf('RPC call was not successful: %s', $error->getMessage()));
    }

    public static function unexpectedResponse()
    {
        return new static('RPC call was successful but contains unexpected response');
    }
}
