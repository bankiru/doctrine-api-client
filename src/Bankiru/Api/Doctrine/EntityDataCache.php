<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

interface EntityDataCache extends CacheConfigurationProvider
{
    /**
     * Returns cached entity source data
     *
     * @param ApiMetadata $metadata
     * @param array       $identifier
     *
     * @return mixed|null Entity source data or NULL if no data cached
     */
    public function get(ApiMetadata $metadata, array $identifier);

    /**
     * Stores entity source data to cache
     *
     * @param mixed       $data Entity source data
     * @param ApiMetadata $metadata
     * @param array       $identifier
     *
     * @return void
     */
    public function set($data, ApiMetadata $metadata, array $identifier);

}
