<?php

namespace Bankiru\Api\Doctrine;

interface EntityCacheAware
{
    /**
     * @param ApiEntityCache $cache
     */
    public function setEntityCache(ApiEntityCache $cache);
}
