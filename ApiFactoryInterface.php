<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface ApiFactoryInterface
{
    public function createApi(RpcClientInterface $client, ApiMetadata $metadata);
}
