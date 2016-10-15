<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCache;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

final class VoidEntityCache implements EntityDataCache
{
    /** {@inheritdoc} */
    public function get(ApiMetadata $metadata, array $identifier)
    {
        return null;
    }

    /** {@inheritdoc} */
    public function set($data, ApiMetadata $metadata, array $identifier)
    {
        // noop
    }

    /** {@inheritdoc} */
    public function getCacheConfiguration(ApiMetadata $metadata)
    {
        return CacheConfiguration::disabled();
    }
}
