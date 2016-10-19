<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCacheInterface;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class VoidEntityCache implements EntityDataCacheInterface
{
    /** @var  ApiMetadata */
    private $metadata;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * VoidEntityCache constructor.
     *
     * @param ApiMetadata     $metadata
     * @param LoggerInterface $logger
     */
    public function __construct(ApiMetadata $metadata, LoggerInterface $logger = null)
    {
        $this->metadata = $metadata;
        $this->logger   = $logger ?: new NullLogger();
    }

    /** {@inheritdoc} */
    public function get(array $identifier)
    {
        $this->logSkip();

        return null;
    }

    /** {@inheritdoc} */
    public function set(array $identifier, $data)
    {
        $this->logSkip();
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

    private function logSkip()
    {
        $this->logger->debug(
            sprintf('Skipping entity cache for %s: not configured', $this->getMetadata()->getName())
        );
    }
}
