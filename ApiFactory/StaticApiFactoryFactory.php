<?php

namespace Bankiru\Api\Doctrine\ApiFactory;

use Bankiru\Api\Doctrine\ApiFactoryRegistryInterface;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class StaticApiFactoryFactory implements ApiFactoryRegistryInterface
{
    /** {@inheritdoc} */
    public function create($alias, RpcClientInterface $client, ApiMetadata $metadata)
    {
        /** @var StaticApiFactoryInterface $alias */
        if (!$this->has($alias)) {
            throw MappingException::unknownApiFactory($alias);
        }

        return $alias::createApi($client, $metadata);

    }

    /** {@inheritdoc} */
    public function has($alias)
    {
        return in_array(StaticApiFactoryInterface::class, class_implements($alias), true);
    }
}
