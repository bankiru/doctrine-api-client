<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 11.04.2016
 * Time: 14:02
 */

namespace Bankiru\Api\Doctrine;

interface EntityCacheAware
{
    /**
     * @param ApiEntityCache $cache
     */
    public function setEntityCache(ApiEntityCache $cache);
}
