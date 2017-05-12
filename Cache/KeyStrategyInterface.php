<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

interface KeyStrategyInterface
{
    /**
     * Returns cache key prefix
     *
     * Should be valid PSR-6 cache key
     *
     * @param ApiMetadata $metadata
     *
     * @return string
     */
    public function getEntityPrefix(ApiMetadata $metadata);

    /**
     * Returns cache key for entity
     *
     * Key should start with prefix defined by KeyStrategyInterface::getEntityPrefix() method
     * Should be valid PSR-6 cache key
     *
     *
     * @see KeyStrategyInterface::getEntityPrefix()
     *
     * @param ApiMetadata $metadata
     * @param array       $identifier
     *
     * @return string
     */
    public function getEntityKey(ApiMetadata $metadata, array $identifier);
}
