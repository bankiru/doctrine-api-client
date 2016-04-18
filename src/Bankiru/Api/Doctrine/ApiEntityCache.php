<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 11.04.2016
 * Time: 8:57
 */

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Utility\IdentifierFlattener;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ApiEntityCache
{
    /** @var  CacheItemPoolInterface */
    private $cache;
    /** @var  ApiEntityManager */
    private $manager;
    /** @var  LoggerInterface */
    private $logger;
    /** @var  IdentifierFlattener */
    private $idFlattener;

    /**
     * ApiEntityCache constructor.
     *
     * @param ApiEntityManager       $manager
     * @param CacheItemPoolInterface $cache
     * @param LoggerInterface        $logger
     */
    public function __construct(
        ApiEntityManager $manager,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger = null
    )
    {
        $this->manager = $manager;
        $this->cache   = $cache;
        $this->logger  = $logger;

        if (null === $this->logger) {
            $this->logger = new NullLogger();
        }

        $this->idFlattener = new IdentifierFlattener($this->manager);
    }


    /**
     * Returns cached entity or null
     *
     * @param string $class
     * @param array  $identifiers
     *
     * @return mixed Entity source data
     */
    public function get($class, array $identifiers)
    {
        $metadata = $this->manager->getClassMetadata($class);

        $configuration = $this->manager->getConfiguration()->getCacheConfiguration($metadata->getName());
        if (null === $configuration || $configuration['enabled'] === false) {
            $this->logger->debug(sprintf('Skipping entity cache for %s: not configured', $metadata->getName()));

            return null;
        }

        $item = $this->cache->getItem($this->getEntityKey($metadata, $identifiers));

        $this->logger->debug(
            sprintf('Entity cache %s', $item->isHit() ? 'HIT' : 'MISS'),
            ['class' => $class, 'identifiers' => $identifiers]
        );

        return $item->get();
    }

    /**
     * Returns key for entity
     *
     * @param ApiMetadata $metadata
     * @param array       $identifiers
     *
     * @return mixed
     */
    private function getEntityKey(ApiMetadata $metadata, array $identifiers)
    {
        $flattenIdentifiers = $this->idFlattener->flattenIdentifier($metadata, $identifiers);

        return sha1((sprintf('%s %s', $metadata->getName(), implode(' ', $flattenIdentifiers))));
    }

    /**
     * Stores entity to cache
     *
     * @param mixed       $data Entity source data
     * @param ApiMetadata $metadata
     * @param array       $identifiers
     *
     * @return bool
     */
    public function set($data, ApiMetadata $metadata, array $identifiers)
    {
        $item          = $this->cache->getItem($this->getEntityKey($metadata, $identifiers));
        $configuration = $this->manager->getConfiguration()->getCacheConfiguration($metadata->getName());

        if (null === $configuration || $configuration['enabled'] === false) {
            $this->logger->debug(sprintf('Skipping entity cache for %s: not configured', $metadata->getName()));

            return;
        }

        $item->set($data);
        $item->expiresAfter($configuration['ttl']);
        $this->cache->save($item);

        $this->logger->debug(
            sprintf('Storing entity %s', $item->isHit() ? 'HIT' : 'MISS'),
            ['class' => $metadata->getName(), 'identifiers' => $identifiers]
        );
    }
}
