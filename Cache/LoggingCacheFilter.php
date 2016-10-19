<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\EntityDataCacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LoggingCacheFilter implements EntityDataCacheInterface
{
    /** @var  EntityDataCacheInterface */
    private $delegate;
    /** @var  LoggerInterface */
    private $logger;

    /**
     * LoggingCacheFilter constructor.
     *
     * @param EntityDataCacheInterface $delegate
     * @param LoggerInterface          $logger
     */
    public function __construct(EntityDataCacheInterface $delegate, LoggerInterface $logger = null)
    {
        $this->delegate = $delegate;
        $this->logger   = $logger ?: new NullLogger();
    }

    /** {@inheritdoc} */
    public function get(array $identifier)
    {
        if (!$this->getConfiguration()->isEnabled()) {
            $this->logSkip();

            return null;
        }

        $data = $this->delegate->get($identifier);

        $this->logger->debug(
            sprintf('Entity cache %s', null === $data ? 'HIT' : 'MISS'),
            ['class' => $this->getMetadata()->getName(), 'identifiers' => $identifier]
        );

        return $data;
    }

    /** {@inheritdoc} */
    public function set(array $identifier, $data)
    {
        if (!$this->getConfiguration()->isEnabled()) {
            $this->logSkip();

            return;
        }

        $this->delegate->set($identifier, $data);

        $this->logger->debug(
            'Stored API entity data to cache',
            ['class' => $this->getMetadata()->getName(), 'identifiers' => $identifier]
        );
    }

    /** {@inheritdoc} */
    public function getConfiguration()
    {
        return $this->delegate->getConfiguration();
    }

    /** {@inheritdoc} */
    public function getMetadata()
    {
        return $this->delegate->getMetadata();
    }

    private function logSkip()
    {
        $this->logger->debug(
            sprintf('Skipping entity cache for %s: not configured', $this->getMetadata()->getName())
        );
    }
}
