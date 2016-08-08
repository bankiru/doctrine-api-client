<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface Searcher
{
    /**
     * Performs search with given RPC client and arguments
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     * @param array              $parameters search arguments, returned by SearchTransformer
     *
     * @return \stdClass[] data for hydration
     */
    public function search(RpcClientInterface $client, ApiMetadata $metadata, array $parameters);
}
