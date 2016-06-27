<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.12.2015
 * Time: 9:39
 */

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ProxyFactory;
use Doctrine\Common\Persistence\ObjectRepository;

class EntityManager implements ApiEntityManager
{
    /** @var EntityMetadataFactory */
    private $metadataFactory;
    /** @var  Configuration */
    private $configuration;
    /** @var ObjectRepository[] */
    private $repositories = [];
    /** @var UnitOfWork */
    private $unitOfWork;
    /** @var ProxyFactory */
    private $proxyFactory;
    /** @var  ApiEntityCache */
    private $entityCache;

    /**
     * EntityManager constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;

        $this->metadataFactory = $configuration->getMetadataFactory();
        $this->metadataFactory->setEntityManager($this);

        $this->unitOfWork   = new UnitOfWork($this);
        $this->proxyFactory = new ProxyFactory($this);

        if (null !== ($cache = $this->configuration->getApiCache())) {
            $this->entityCache = new ApiEntityCache(
                $this,
                $cache,
                $this->configuration->getApiCacheLogger()
            );
        }
    }

    /** {@inheritdoc} */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /** {@inheritdoc} */
    public function getEntityCache()
    {
        return $this->entityCache;
    }

    /** {@inheritdoc} */
    public function find($className, $id)
    {
        $id = $this->fixScalarId($id, $className);

        /** @var EntityMetadata $metadata */
        $metadata = $this->getClassMetadata($className);
        if (false !== ($entity = $this->getUnitOfWork()->tryGetById($id, $metadata->rootEntityName))) {
            return $entity instanceof $metadata->name ? $entity : null;
        }

        return $this->getUnitOfWork()->getEntityPersister($className)->loadById($id);
    }

    /**
     * @param array|mixed $id
     *
     * @return array
     * @throws MappingException
     */
    private function fixScalarId($id, $className)
    {
        if (is_array($id)) {
            return $id;
        }

        $id = (array)$id;

        $identifiers = $this->getClassMetadata($className)->getIdentifierFieldNames();
        if (count($id) !== count($identifiers)) {
            throw MappingException::invalidIdentifierStructure();
        }

        return array_combine($identifiers, (array)$id);
    }

    /**
     * {@inheritdoc}
     * @return ApiMetadata
     */
    public function getClassMetadata($className)
    {
        return $this->getMetadataFactory()->getMetadataFor($className);
    }

    /** {@inheritdoc} */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /** {@inheritdoc} */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /** {@inheritdoc} */
    public function persist($object)
    {
        throw new \BadMethodCallException('Persisting object is not supported');
    }

    /** {@inheritdoc} */
    public function remove($object)
    {
        //Todo: support object deletion via API (@scaytrase)
        throw new \BadMethodCallException('Removing object is not supported');
    }

    /** {@inheritdoc} */
    public function merge($object)
    {
        throw new \BadMethodCallException('Merge is not supported');
    }

    /** {@inheritdoc} */
    public function clear($objectName = null)
    {
        throw new \BadMethodCallException('Clearing EM is not supported');
    }

    /** {@inheritdoc} */
    public function detach($object)
    {
        throw new \BadMethodCallException('Detach object is not supported');
    }

    /** {@inheritdoc} */
    public function refresh($object)
    {
        $this->getRepository(get_class($object))->find($object->getId());
    }

    /** {@inheritdoc} */
    public function getRepository($className)
    {
        if (!array_key_exists($className, $this->repositories)) {
            /** @var ApiMetadata $metadata */
            $metadata        = $this->getClassMetadata($className);
            $repositoryClass = $metadata->getRepositoryClass();
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->repositories[$className] = new $repositoryClass($this, $className);
        }

        return $this->repositories[$className];
    }

    /** {@inheritdoc} */
    public function flush()
    {
        throw new \BadMethodCallException('Flush is not supported');
    }

    /** {@inheritdoc} */
    public function initializeObject($obj)
    {
        // Todo: generate proxy class here (@scaytrase)
        return;
    }

    /** {@inheritdoc} */
    public function contains($object)
    {
        return false;
    }

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @param string $entityName The name of the entity type.
     * @param mixed  $id         The entity identifier.
     *
     * @return object The entity reference.
     */
    public function getReference($entityName, $id)
    {
        $id = $this->fixScalarId($id, $entityName);

        /** @var EntityMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);
        if (false !== ($entity = $this->getUnitOfWork()->tryGetById($id, $metadata->rootEntityName))) {
            return $entity instanceof $metadata->name ? $entity : null;
        }

        $proxy = $this->getProxyFactory()->getProxy($entityName, $id);
        $this->getUnitOfWork()->registerManaged($proxy, $id, null);

        return $proxy;
    }

    /** {@inheritdoc} */
    public function getProxyFactory()
    {
        return $this->proxyFactory;
    }
}
