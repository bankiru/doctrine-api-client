<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

interface KeyStrategyInterface
{
    /**
     * Returns cache key prefix
     *
     * @param ApiMetadata $metadata
     *
     * @return string
     */
    public function getEntityPrefix(ApiMetadata $metadata);

    /**
     * Returns cache key for entity. Key should start with prefix
     * defined by KeyStrategyInterface::getEntityPrefix method
     *
     * @param ApiMetadata $metadata
     * @param array       $identifier
     *
     * @return string
     */
    public function getEntityKey(ApiMetadata $metadata,array $identifier);
}
