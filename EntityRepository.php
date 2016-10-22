<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Doctrine\Common\Persistence\ObjectRepository;
use ScayTrase\Api\Rpc\RpcClientInterface;

class EntityRepository implements ObjectRepository
{
    /** @var  ApiMetadata */
    private $metadata;
    /** @var EntityManager */
    private $manager;

    /**
     * EntityRepository constructor.
     *
     * @param EntityManager $manager
     * @param string        $className
     */
    public function __construct(EntityManager $manager, $className)
    {
        $this->manager  = $manager;
        $this->metadata = $this->manager->getClassMetadata($className);
    }

    /**
     * Finds an object by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     *
     * @return object The object.
     */
    public function find($id)
    {
        return $this->manager->find($this->getClassName(), $id);
    }

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->metadata->getReflectionClass()->getName();
    }

    /**
     * Finds all objects in the repository.
     *
     * @return array The objects.
     */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array The objects.
     *
     * @throws \UnexpectedValueException
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $persister = $this->manager->getUnitOfWork()->getEntityPersister($this->getClassName());

        return $persister->loadAll($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria The criteria.
     *
     * @return object The object.
     */
    public function findOneBy(array $criteria)
    {
        $objects = $this->findBy($criteria, [], 1);

        return array_shift($objects);
    }

    /**
     * @return EntityManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @return RpcClientInterface
     */
    protected function getClient()
    {
        return $this->manager->getConfiguration()->getClientRegistry()->get($this->metadata->getClientName());
    }

    /**
     * @return ApiMetadata
     */
    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Hydrates object from given data or merges it to already fetched object
     *
     * @param mixed $data
     *
     * @return object
     */
    protected function hydrateObject($data)
    {
        return $this->getManager()->getUnitOfWork()->getOrCreateEntity($this->getClassName(), $data);
    }

    /**
     * @param string $alias
     *
     * @return string
     * @throws \OutOfBoundsException if no method exist
     */
    protected function getClientMethod($alias)
    {
        return $this->getMetadata()->getMethodContainer()->getMethod($alias);
    }
}
