<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.12.2015
 * Time: 11:11
 */

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
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
        return new ApiCollection($this->manager, $this->metadata, [$criteria, $orderBy, $limit, $offset]);
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
     * @return RpcClientInterface
     */
    protected function getClient()
    {
        return $this->manager->getConfiguration()->getRegistry()->get($this->metadata->getClientName());
    }

    /**
     * @return ApiMetadata
     */
    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @return EntityManager
     */
    public function getManager()
    {
        return $this->manager;
    }
}
