<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCache;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LoggingCacheFilter implements EntityDataCache
{
    /** @var  EntityDataCache */
    private $delegate;
    /** @var  LoggerInterface */
    private $logger;

    /**
     * LoggingCacheFilter constructor.
     *
     * @param EntityDataCache $delegate
     * @param LoggerInterface $logger
     */
    public function __construct(EntityDataCache $delegate, LoggerInterface $logger = null)
    {
        $this->delegate = $delegate;
        $this->logger   = $logger ?: new NullLogger();
    }


    /** {@inheritdoc} */
    public function get(ApiMetadata $metadata, array $identifier)
    {
        if (!$this->getCacheConfiguration($metadata)->isEnabled()) {
            $this->logger->debug(sprintf('Skipping entity cache for %s: not configured', $metadata->getName()));

            return null;
        }

        $data = $this->delegate->get($metadata, $identifier);

        $this->logger->debug(
            sprintf('Entity cache %s', null === $data ? 'HIT' : 'MISS'),
            ['class' => $metadata->getName(), 'identifiers' => $identifier]
        );

        return $data;
    }

    /** {@inheritdoc} */
    public function set($data, ApiMetadata $metadata, array $identifier)
    {
        if (!$this->getCacheConfiguration($metadata)->isEnabled()) {
            $this->logger->debug(sprintf('Skipping entity cache for %s: not configured', $metadata->getName()));

            return;
        }

        $this->delegate->set($data, $metadata, $identifier);

        $this->logger->debug('Storing entity', ['class' => $metadata->getName(), 'identifiers' => $identifier]);
    }

    /** {@inheritdoc} */
    public function getCacheConfiguration(ApiMetadata $metadata)
    {
        return $this->delegate->getCacheConfiguration($metadata);
    }
}
