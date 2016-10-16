<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface Patcher
{
    /**
     * Performs update of the entity. If API does not support PATCH-like request - just ignore fields argument
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     * @param object             $entity Instance of metadata-described entity
     * @param string[]           $fields List of modified fields
     *
     * @return int objects count
     */
    public function patch(RpcClientInterface $client, ApiMetadata $metadata, $entity, array $fields);
}
