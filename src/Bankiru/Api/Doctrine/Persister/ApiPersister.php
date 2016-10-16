<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManagerAware;
use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Rpc\CachedFinder;
use Bankiru\Api\Doctrine\Rpc\Counter;
use Bankiru\Api\Doctrine\Rpc\Finder;
use Bankiru\Api\Doctrine\Rpc\SearchArgumentsTransformer;
use Bankiru\Api\Doctrine\Rpc\Searcher;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ScayTrase\Api\Rpc\RpcClientInterface;

/** @internal */
class ApiPersister implements EntityPersister
{
    /** @var  RpcClientInterface */
    private $client;
    /** @var  EntityMetadata */
    private $metadata;
    /** @var EntityManager */
    private $manager;

    /**
     * ApiPersister constructor.
     *
     * @param EntityManager $manager
     * @param ApiMetadata   $metadata
     */
    public function __construct(EntityManager $manager, ApiMetadata $metadata)
    {
        $this->manager  = $manager;
        $this->client   = $manager->getConfiguration()->getRegistry()->get($metadata->getClientName());
        $this->metadata = $metadata;
    }

    /**
     * @return ApiMetadata
     */
    public function getClassMetadata()
    {
        return $this->metadata;
    }

    /**
     * Updates a managed entity. The entity is updated according to its current changeset
     * in the running UnitOfWork. If there is no changeset, nothing is updated.
     *
     * @param object $entity The entity to update.
     *
     * @return void
     */
    public function update($entity)
    {
        throw new \BadMethodCallException('Update method is not supported currently');
    }

    /**
     * Deletes a managed entity.
     *
     * The entity to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of entity deletion.
     *
     * @param object $entity The entity to delete.
     *
     * @return bool TRUE if the entity got deleted in the database, FALSE otherwise.
     */
    public function delete($entity)
    {
        throw new \BadMethodCallException('Delete method is not supported currently');
    }

    /**
     * Count entities (optionally filtered by a criteria)
     *
     * @param  array|\Doctrine\Common\Collections\Criteria $criteria
     *
     * @return int
     */
    public function count($criteria = [])
    {
        $transformer = new SearchArgumentsTransformer($this->metadata, $this->manager);
        $parameters  = $transformer->transform($criteria);

        return $this->createCounter()->count($this->getClient(), $this->metadata, $parameters['criteria']);
    }

    /**
     * Loads a list of entities by a list of field criteria.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return Collection
     */
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $transformer = new SearchArgumentsTransformer($this->metadata, $this->manager);

        $objects = $this->createSearcher()->search(
            $this->getClient(),
            $this->metadata,
            $transformer->transform($criteria, $orderBy, $limit, $offset)
        );

        if (!$objects instanceof \Traversable) {
            $objects = new \ArrayIterator($objects);
        }

        $entities = [];
        foreach ($objects as $object) {
            $entities[] = $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $object);
        }

        return new ArrayCollection($entities);
    }

    /**
     * Loads an entity of this persister's mapped class as part of a single-valued
     * association from another entity.
     *
     * @param array  $assoc        The association to load.
     * @param object $sourceEntity The entity that owns the association (not necessarily the "owning side").
     * @param array  $identifier   The identifier of the entity to load. Must be provided if
     *                             the association to load represents the owning side, otherwise
     *                             the identifier is derived from the $sourceEntity.
     *
     * @return object The loaded and managed entity instance or NULL if the entity can not be found.
     */
    public function loadOneToOneEntity(array $assoc, $sourceEntity, array $identifier = [])
    {
        if (false !== ($foundEntity = $this->manager->getUnitOfWork()->tryGetById($identifier, $assoc['target']))) {
            return $foundEntity;
        }

        // Get identifiers from entity if the entity is not the owning side
        if (!$assoc['isOwningSide']) {
            $identifier = $this->metadata->getIdentifierValues($sourceEntity);
        }

        return $this->loadById($identifier);
    }

    public function loadById(array $identifiers, $entity = null)
    {
        $body = $this->createFinder()->find($this->client, $this->metadata, $identifiers);

        if (null === $body) {
            return null;
        }

        $entity = $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $body, $entity);

        return $entity;
    }

    /**
     * Refreshes a managed entity.
     *
     * @param array    $id       The identifier of the entity as an associative array from
     *                           column or field names to values.
     * @param object   $entity   The entity to refresh.
     * @param int|null $lockMode One of the \Doctrine\DBAL\LockMode::* constants
     *                           or NULL if no specific lock mode should be used
     *                           for refreshing the managed entity.
     *
     * @return void
     */
    public function refresh(array $id, $entity, $lockMode = null)
    {
        throw new \BadMethodCallException('Refresh method is not supported currently');
    }

    /**
     * Loads a collection of entities in a one-to-many association.
     *
     * @param array                  $assoc
     * @param object                 $sourceEntity
     * @param AbstractLazyCollection $collection The collection to load/fill.
     *
     * @return Collection
     */
    public function loadOneToManyCollection(array $assoc, $sourceEntity, AbstractLazyCollection $collection)
    {
        if ($collection instanceof ApiCollection) {
            foreach ($collection->getIterator() as $entity) {
                $this->metadata->getReflectionProperty($assoc['mappedBy'])->setValue($entity, $sourceEntity);
            }
        }

        return $collection;
    }

    /**
     * Returns an array with (sliced or full list) of elements in the specified collection.
     *
     * @param array    $assoc
     * @param object   $sourceEntity
     * @param int|null $limit
     *
     * @param int|null $offset
     *
     * @return Collection
     */
    public function getOneToManyCollection(array $assoc, $sourceEntity, $limit = null, $offset = null)
    {
        $targetClass = $assoc['target'];
        /** @var EntityMetadata $targetMetadata */
        $targetMetadata = $this->manager->getClassMetadata($targetClass);

        if ($this->metadata->isIdentifierComposite) {
            throw new \BadMethodCallException(__METHOD__.' on composite reference is not supported');
        }

        $criteria = [
            $assoc['mappedBy'] => $sourceEntity,
        ];

        $orderBy = isset($assoc['orderBy']) ? $assoc['orderBy'] : [];

        return new ApiCollection(
            $this->manager,
            $targetMetadata,
            [$criteria, $orderBy, $limit, $offset]
        );
    }

    public function getToOneEntity(array $mapping, $sourceEntity, array $identifiers)
    {
        $metadata = $this->manager->getClassMetadata(get_class($sourceEntity));

        if (!$mapping['isOwningSide']) {
            $identifiers = $metadata->getIdentifierValues($sourceEntity);
        }

        return $this->manager->getReference($mapping['target'], $identifiers);
    }

    /**
     * @return Finder
     */
    public function createFinder()
    {
        $finderClass = $this->metadata->getFinderClass();
        /** @var Finder $finder */
        $finder = new $finderClass();

        if ($finder instanceof ApiEntityManagerAware) {
            $finder->setApiEntityManager($this->manager);
        }

        return new CachedFinder($finder, $this->manager->getEntityCache());
    }

    /**
     * @return Searcher
     */
    public function createSearcher()
    {
        /** @var Searcher $searcher */
        $searcherClass = $this->metadata->getSearcherClass();
        $searcher      = new $searcherClass($this->manager);

        if ($searcher instanceof ApiEntityManagerAware) {
            $searcher->setApiEntityManager($this->manager);
        }

        return $searcher;
    }

    /**
     * @return Counter
     */
    public function createCounter()
    {
        /** @var Counter $counter */
        $counterClass = $this->metadata->getCounterClass();
        $counter      = new $counterClass($this->manager);

        if ($counter instanceof ApiEntityManagerAware) {
            $counter->setApiEntityManager($this->manager);
        }

        return $counter;
    }

    /**
     * @return RpcClientInterface
     */
    private function getClient()
    {
        return $this->manager->getConfiguration()->getRegistry()->get($this->metadata->getClientName());
    }
}
