<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 22.03.2016
 * Time: 12:35
 */

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface Finder
{
    /**
     * Performs search with given RPC client and arguments
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     * @param array              $identifiers array of identifiers
     *
     * @return \stdClass data for hydration
     */
    public function find(RpcClientInterface $client, ApiMetadata $metadata, array $identifiers);
}
