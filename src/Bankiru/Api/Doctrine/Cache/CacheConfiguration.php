<?php

namespace Bankiru\Api\Doctrine\Cache;

class CacheConfiguration
{
    /** @var int|null */
    private $ttl;
    /** @var bool */
    private $enabled = false;

    private function __construct()
    {
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public static function create(array $data)
    {
        $configuration          = new static();
        $configuration->ttl     = $data['ttl'];
        $configuration->enabled = $data['enabled'];

        return $configuration;
    }

    /**
     * Constructor for disabled cache entities configuration
     *
     * @return static
     */
    public static function disabled()
    {
        $configuration          = new static();
        $configuration->enabled = false;

        return $configuration;
    }

    /**
     * @return int|null
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
}
