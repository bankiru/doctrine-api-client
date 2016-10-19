<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface EntityApiInterface
{
    /** @return RpcClientInterface */
    public function getClient();

    /** @return ApiMetadata */
    public function getMetadata();
}
