<?php

namespace Bankiru\Api\Doctrine\ApiFactory;

use Bankiru\Api\Doctrine\ApiFactoryRegistryInterface;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class ChainApiFactoryRegistry implements ApiFactoryRegistryInterface
{
    /** @var ApiFactoryRegistryInterface[] */
    private $delegates = [];

    /**
     * ChainApiFactory constructor.
     *
     * @param array $delegates
     */
    public function __construct(array $delegates = [])
    {
        $this->delegates = $delegates;
    }

    /** {@inheritdoc} */
    public function create($alias, RpcClientInterface $client, ApiMetadata $metadata)
    {
        foreach ($this->delegates as $delegate) {
            if ($delegate->has($alias)) {
                return $delegate->create($alias, $client, $metadata);
            }
        }

        throw MappingException::unknownApiFactory($alias);
    }

    /** {@inheritdoc} */
    public function has($alias)
    {
        foreach ($this->delegates as $delegate) {
            if ($delegate->has($alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ApiFactoryRegistryInterface $registry
     */
    public function add(ApiFactoryRegistryInterface $registry)
    {
        $this->delegates[] = $registry;
    }
}
