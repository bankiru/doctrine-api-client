<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Cache\CacheConfiguration;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

interface EntityDataCacheInterface
{
    /**
     * Returns cached entity source data
     *
     * @param array $identifier
     *
     * @return mixed|null Entity source data or NULL if no data cached
     */
    public function get(array $identifier);

    /**
     * Stores entity source data to cache
     *
     * @param mixed $data Entity source data
     * @param array $identifier
     *
     * @return void
     */
    public function set(array $identifier, $data);

    /**
     * @return ApiMetadata
     */
    public function getMetadata();
}
