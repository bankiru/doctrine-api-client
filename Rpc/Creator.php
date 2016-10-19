<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface Creator extends EntityApiInterface
{
    /**
     * Creates the entity via API request. Should receive the ID back as a part of response
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     * @param array              $data
     *
     * @return int objects count
     */
    public function create(RpcClientInterface $client, ApiMetadata $metadata, array $data);
}
