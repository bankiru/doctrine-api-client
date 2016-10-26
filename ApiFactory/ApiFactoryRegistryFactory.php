<?php

namespace Bankiru\Api\Doctrine\ApiFactory;

use Bankiru\Api\Doctrine\ApiFactoryInterface;
use Bankiru\Api\Doctrine\ApiFactoryRegistryInterface;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class ApiFactoryRegistryFactory implements ApiFactoryRegistryInterface
{
    /** @var  ApiFactoryInterface[] */
    private $factories = [];

    /** {@inheritdoc} */
    public function create($alias, RpcClientInterface $client, ApiMetadata $metadata)
    {
        if (!$this->has($alias)) {
            throw MappingException::unknownApiFactory($alias);
        }

        return $this->factories[$alias]->createApi($client, $metadata);
    }

    /** {@inheritdoc} */
    public function has($alias)
    {
        return array_key_exists($alias, $this->factories);
    }

    /** {@inheritdoc} */
    public function set($alias, ApiFactoryInterface $factory)
    {
        $this->factories[$alias] = $factory;
    }
}
