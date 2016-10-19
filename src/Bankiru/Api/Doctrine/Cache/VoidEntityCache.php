<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCacheInterface;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

final class VoidEntityCache implements EntityDataCacheInterface
{
    /** @var  ApiMetadata */
    private $metadata;

    /**
     * VoidEntityCache constructor.
     *
     * @param ApiMetadata $metadata
     */
    public function __construct(ApiMetadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /** {@inheritdoc} */
    public function get(array $identifier)
    {
        return null;
    }

    /** {@inheritdoc} */
    public function set(array $identifier, $data)
    {
        // noop
    }

    /** {@inheritdoc} */
    public function getConfiguration()
    {
        return CacheConfiguration::disabled();
    }

    /** {@inheritdoc} */
    public function getMetadata()
    {
        return $this->metadata;
    }
}
