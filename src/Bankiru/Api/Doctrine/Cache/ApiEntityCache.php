<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\CacheConfigurationProvider;
use Bankiru\Api\Doctrine\EntityDataCache;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Psr\Cache\CacheItemPoolInterface;

class ApiEntityCache implements EntityDataCache
{
    /** @var  CacheItemPoolInterface */
    private $cache;
    /** @var KeyStrategyInterface */
    private $strategy;
    /** @var ConfigurationProvider */
    private $provider;

    /**
     * ApiEntityCache constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param ConfigurationProvider  $provider
     * @param KeyStrategyInterface   $strategy
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        CacheConfigurationProvider $provider,
        KeyStrategyInterface $strategy
    ) {
        $this->cache    = $cache;
        $this->strategy = $strategy;
        $this->provider = $provider;
    }


    /** {@inheritdoc} */
    public function get(ApiMetadata $metadata, array $identifier)
    {
        return $this->cache->getItem($this->strategy->getEntityKey($metadata, $identifier))->get();
    }

    /** {@inheritdoc} */
    public function set($data, ApiMetadata $metadata, array $identifier)
    {
        $this->cache->save(
            $this->cache
                ->getItem($this->strategy->getEntityKey($metadata, $identifier))
                ->set($data)
                ->expiresAfter($this->getCacheConfiguration($metadata)->getTtl())
        );
    }

    /** {@inheritdoc} */
    public function getCacheConfiguration(ApiMetadata $metadata)
    {
        return $this->provider->getCacheConfiguration($metadata);
    }
}
