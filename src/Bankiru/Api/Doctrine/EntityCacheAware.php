<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Cache\ApiEntityDataCache;

interface EntityCacheAware
{
    /**
     * @param ApiEntityDataCache $cache
     */
    public function setEntityCache(ApiEntityDataCache $cache);
}
