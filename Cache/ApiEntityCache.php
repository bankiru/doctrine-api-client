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
    /** @var CacheConfiguration */
    private $configuration;

    /**
     * ApiEntityCache constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param ApiMetadata            $metadata
     * @param CacheConfiguration     $configuration
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        ApiMetadata $metadata,
        CacheConfiguration $configuration
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

    /**
     * @param array $identifier
     *
     * @return string
     */
    private function getKey(array $identifier)
    {
        return $this->configuration->getStrategy()->getEntityKey($this->metadata, $identifier);
    }
}
