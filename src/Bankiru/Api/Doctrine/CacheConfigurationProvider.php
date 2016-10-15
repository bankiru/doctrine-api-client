<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Cache\CacheConfiguration;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

interface CacheConfigurationProvider
{
    /**
     * Returns cache configuration for entity
     *
     * @param ApiMetadata $metadata
     *
     * @return CacheConfiguration
     */
    public function getCacheConfiguration(ApiMetadata $metadata);
}
