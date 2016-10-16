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
     * @param array              $parameters doctrine FindBy transformed arguments
     *
     * @return \stdClass[]|\Traversable data for hydration
     */
    public function search(RpcClientInterface $client, ApiMetadata $metadata, array $parameters);
}
