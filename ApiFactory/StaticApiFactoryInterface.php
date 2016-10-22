<?php

namespace Bankiru\Api\Doctrine\ApiFactory;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface StaticApiFactoryInterface
{
    /**
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     *
     * @return CrudsApiInterface
     */
    public static function createApi(RpcClientInterface $client, ApiMetadata $metadata);
}
