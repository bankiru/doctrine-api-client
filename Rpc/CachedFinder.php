<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\EntityDataCacheInterface;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class CachedFinder implements Finder
{
    /** @var  Finder */
    private $delegate;
    /** @var  EntityDataCacheInterface */
    private $cache;

    /**
     * CachedFinder constructor.
     *
     * @param Finder                   $delegate
     * @param EntityDataCacheInterface $cache
     */
    public function __construct(Finder $delegate, EntityDataCacheInterface $cache)
    {
        $this->delegate = $delegate;
        $this->cache    = $cache;
    }

    /** {@inheritdoc} */
    public function find(array $identifier)
    {
        $body = $this->cache->get($identifier);
        if (null !== $body) {
            return $body;
        }

        $body = $this->delegate->find($identifier);
        $this->cache->set($body, $identifier);

        return $body;
    }

    /** {@inheritdoc} */
    public function getClient()
    {
        return $this->delegate->getClient();
    }

    /** {@inheritdoc} */
    public function getMetadata()
    {
        return $this->delegate->getMetadata();
    }
}
