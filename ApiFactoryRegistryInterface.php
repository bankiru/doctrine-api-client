<?php
namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Exception\ApiFactoryRegistryException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface ApiFactoryRegistryInterface
{
    /**
     * @param string             $alias
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     *
     * @return CrudsApiInterface
     *
     * @throws ApiFactoryRegistryException
     */
    public function create($alias, RpcClientInterface $client, ApiMetadata $metadata);

    /**
     * @param string $alias
     *
     * @return bool
     */
    public function has($alias);
}
