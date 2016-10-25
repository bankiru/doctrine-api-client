<?php
namespace Bankiru\Api\Doctrine\Cache;

interface CacheConfigurationInterface
{
    /**
     * @return KeyStrategyInterface|null
     */
    public function getStrategy();

    /**
     * @return int|null
     */
    public function getTtl();

    /**
     * @return boolean
     */
    public function isEnabled();

    /**
     * Returns extra configuration payload by key if present
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function extra($key);
}
