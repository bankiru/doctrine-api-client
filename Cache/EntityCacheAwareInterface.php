<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCacheInterface;

interface EntityCacheAwareInterface
{
    /**
     * Configures entity cache
     *
     * @param EntityDataCacheInterface $cache
     *
     * @return void
     */
    public function setEntityCache(EntityDataCacheInterface $cache);
}
