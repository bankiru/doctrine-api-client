<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\EntityDataCache;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class CachedFinder implements Finder
{
    /** @var  Finder */
    private $delegate;
    /** @var  EntityDataCache */
    private $cache;

    /**
     * CachedFinder constructor.
     *
     * @param Finder          $delegate
     * @param EntityDataCache $cache
     */
    public function __construct(Finder $delegate, EntityDataCache $cache)
    {
        $this->delegate = $delegate;
        $this->cache    = $cache;
    }

    /** {@inheritdoc} */
    public function find(RpcClientInterface $client, ApiMetadata $metadata, array $identifier)
    {
        $body = $this->cache->get($metadata, $identifier);
        if (null !== $body) {
            return $body;
        }

        $body = $this->delegate->find($client, $metadata, $identifier);
        $this->cache->set($body, $metadata, $identifier);

        return $body;
    }
}
