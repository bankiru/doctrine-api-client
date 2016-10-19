<?php

namespace Bankiru\Api\Doctrine\ApiFactory;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface StaticApiFactoryInterface
{
    /**
     * Creates API from client and metadata
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     *
     * @return mixed
     */
    public static function createApi(RpcClientInterface $client, ApiMetadata $metadata);
}
