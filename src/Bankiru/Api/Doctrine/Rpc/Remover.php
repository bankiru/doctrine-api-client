<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface Remover
{
    /**
     * Creates the entity via API request. Should receive the ID back as a part of response
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     * @param array              $identifier Instance of metadata-described entity
     */
    public function remove(RpcClientInterface $client, ApiMetadata $metadata, array $identifier);
}
