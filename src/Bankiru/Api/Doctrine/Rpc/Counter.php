<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 22.03.2016
 * Time: 12:53
 */

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface Counter
{
    /**
     * Performs search with given RPC client and arguments
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     * @param array              $parameters search arguments, returned by SearchTransformer
     *
     * @return int objects count
     */
    public function count(RpcClientInterface $client, ApiMetadata $metadata, array $parameters);
}
