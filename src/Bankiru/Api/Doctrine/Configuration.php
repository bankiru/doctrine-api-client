<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.12.2015
 * Time: 16:20
 */

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\ClientRegistryInterface;
use Bankiru\Api\Doctrine\Type\TypeRegistryInterface;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Configuration
{
    /** @var  EntityMetadataFactory */
    private $metadataFactory;
    /** @var  MappingDriver */
    private $driver;
    /** @var  ClientRegistryInterface */
    private $registry;
    /** @var  string */
    private $proxyDir;
    /** @var  string */
    private $proxyNamespace;
    /** @var bool */
    private $autogenerateProxies = true;
    /** @var  TypeRegistryInterface */
    private $typeRegistry;
    /** @var  array */
    private $cacheConfiguration = [];
    /** @var  CacheItemPoolInterface */
    private $apiCache;
    /** @var  LoggerInterface */
    private $apiCacheLogger;

    /**
     * Configuration constructor.
     */
    public function __construct() {
        $this->apiCacheLogger = new NullLogger();
    }


    /**
     * @return LoggerInterface
     */
    public function getApiCacheLogger()
    {
        return $this->apiCacheLogger;
    }

    /**
     * @param LoggerInterface $apiCacheLogger
     */
    public function setApiCacheLogger($apiCacheLogger)
    {
        $this->apiCacheLogger = $apiCacheLogger;
    }

    /**
     * @return CacheItemPoolInterface
     */
    public function getApiCache()
    {
        return $this->apiCache;
    }

    /**
     * @param CacheItemPoolInterface|null $apiCache
     */
    public function setApiCache(CacheItemPoolInterface $apiCache = null)
    {
        $this->apiCache = $apiCache;
    }

    /**
     * Returns class cache configuration
     *
     * @param $class
     *
     * @return array|null Null if cache is not enabled for this class (use implementation defaults)
     */
    public function getCacheConfiguration($class)
    {
        if (!array_key_exists($class, $this->cacheConfiguration)) {
            return null;
        }

        return $this->cacheConfiguration[$class];
    }

    /**
     * @param string $class
     * @param array  $options
     */
    public function setCacheConfiguration($class, array $options = null)
    {
        $this->cacheConfiguration[$class] = $options;

        if (null === $this->cacheConfiguration[$class]) {
            unset($this->cacheConfiguration[$class]);
        }
    }

    /**
     * @return TypeRegistryInterface
     */
    public function getTypeRegistry()
    {
        return $this->typeRegistry;
    }

    /**
     * @param TypeRegistryInterface $typeRegistry
     */
    public function setTypeRegistry(TypeRegistryInterface $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @return string
     */
    public function getProxyDir()
    {
        return $this->proxyDir;
    }

    /**
     * @param string $proxyDir
     */
    public function setProxyDir($proxyDir)
    {
        $this->proxyDir = $proxyDir;
    }

    /**
     * @return string
     */
    public function getProxyNamespace()
    {
        return $this->proxyNamespace;
    }

    /**
     * @param string $proxyNamespace
     */
    public function setProxyNamespace($proxyNamespace)
    {
        $this->proxyNamespace = $proxyNamespace;
    }

    /**
     * @return boolean
     */
    public function isAutogenerateProxies()
    {
        return $this->autogenerateProxies;
    }

    /**
     * @param boolean $autogenerateProxies
     */
    public function setAutogenerateProxies($autogenerateProxies)
    {
        $this->autogenerateProxies = $autogenerateProxies;
    }

    /**
     * @return ClientRegistryInterface
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @param ClientRegistryInterface $registry
     */
    public function setRegistry(ClientRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return MappingDriver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param MappingDriver $driver
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;
    }

    /**
     * @return EntityMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @param string $metadataFactory
     */
    public function setMetadataFactory($metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }
}
