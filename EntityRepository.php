<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Persister\CollectionMatcher;
use Bankiru\Api\Doctrine\Proxy\LazyCriteriaCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Persistence\ObjectRepository;
use ScayTrase\Api\Rpc\RpcClientInterface;

class EntityRepository implements ObjectRepository, Selectable
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

    /** {@inheritdoc} */
    public function find($id)
    {
        return $this->manager->find($this->getClassName(), $id);
    }

    /** {@inheritdoc} */
    public function getClassName()
    {
        return $this->metadata->getReflectionClass()->getName();
    }

    /** {@inheritdoc} */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /** {@inheritdoc} */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $persister = $this->manager->getUnitOfWork()->getEntityPersister($this->getClassName());

        return $persister->loadAll($criteria, $orderBy, $limit, $offset);
    }

    /** {@inheritdoc} */
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

    /** {@inheritdoc} */
    public function matching(Criteria $criteria)
    {
        return new LazyCriteriaCollection(new CollectionMatcher($this->getManager(), $this->getApi()), $criteria);
    }

    /**
     * @return RpcClientInterface
     */
    protected function getClient()
    {
        return $this->manager->getConfiguration()->getClientRegistry()->get($this->metadata->getClientName());
    }

    /**
     * @return CrudsApiInterface
     */
    protected function getApi()
    {
        return $this->manager->getUnitOfWork()->getEntityPersister($this->metadata->getName())->getCrudsApi();
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
