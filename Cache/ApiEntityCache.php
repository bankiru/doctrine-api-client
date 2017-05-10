<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCacheInterface;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Psr\Cache\CacheItemPoolInterface;

class ApiEntityCache implements EntityDataCacheInterface
{
    /** @var  CacheItemPoolInterface */
    private $cache;
    /** @var ApiMetadata */
    private $metadata;
    /** @var CacheConfigurationInterface */
    private $configuration;

    /**
     * ApiEntityCache constructor.
     *
     * @param CacheItemPoolInterface      $cache
     * @param ApiMetadata                 $metadata
     * @param CacheConfigurationInterface $configuration
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        ApiMetadata $metadata,
        CacheConfigurationInterface $configuration
    ) {
        $this->cache         = $cache;
        $this->metadata      = $metadata;
        $this->configuration = $configuration;
    }

    /** {@inheritdoc} */
    public function get(array $identifier)
    {
        if (!$this->configuration->isEnabled()) {
            return null;
        }

        return $this->cache->getItem($this->getKey($identifier))->get();
    }

    /** {@inheritdoc} */
    public function set(array $identifier, $data)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->cache->save(
            $this->cache
                ->getItem($this->getKey($identifier))
                ->set($data)
                ->expiresAfter($this->configuration->getTtl())
        );
    }

    /** {@inheritdoc} */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /** {@inheritdoc} */
    public function getConfiguration()
    {
        return $this->configuration;
    }


    /**
     * @param array $identifier
     *
     * @return string
     */
    private function getKey(array $identifier)
    {
        return $this->configuration->getStrategy()->getEntityKey($this->metadata, $identifier);
    }

    /**
     * Clears the cache for given entity identifier
     *
     * @param array $identifier
     *
     * @return void
     */
    public function clear(array $identifier)
    {
        $this->cache->deleteItem($this->getKey($identifier));
    }
}
