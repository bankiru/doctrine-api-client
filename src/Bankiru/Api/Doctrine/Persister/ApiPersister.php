<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 02.02.2016
 * Time: 14:30
 */

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Rpc\Finder;
use Bankiru\Api\Doctrine\Rpc\Searcher;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use ScayTrase\Api\Rpc\RpcClientInterface;

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
        return count($this->loadAll($criteria));
    }

    /**
     * Loads a list of entities by a list of field criteria.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array
     */
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $client = $this->manager->getConfiguration()->getRegistry()->get($this->metadata->getClientName());

        /** @var Searcher $searcher */
        $searcherClass = $this->metadata->getSearcherClass();
        $searcher      = new $searcherClass($this->manager);

        $apiCriteria = [];
        foreach ($criteria as $field => $values) {
            if ($this->metadata->hasAssociation($field)) {
                $mapping = $this->metadata->getAssociationMapping($field);
                /** @var EntityMetadata $target */
                $target = $this->manager->getClassMetadata($mapping['target']);

                $converter = function ($value) use ($target) {
                    if (!is_object($value)) {
                        return $value;
                    }

                    $ids = $target->getIdentifierValues($value);
                    if ($target->isIdentifierComposite) {
                        return $ids;
                    }

                    return array_shift($ids);
                };

                if (is_array($values)) {
                    $values = array_map($converter, $values);
                } else {
                    $values = $converter($values);
                }
            }
            $apiCriteria[$this->metadata->getApiFieldName($field)] = $values;
        }

        $apiOrder = [];
        foreach ((array)$orderBy as $field => $direction) {
            $apiOrder[$this->metadata->getApiFieldName($field)] = $direction;
        }

        $objects = $searcher->search(
            $client,
            $this->metadata,
            [
                $apiCriteria,
                $apiOrder,
                $limit,
                $offset,
            ]
        );

        $entities = [];
        foreach ($objects as $object) {
            $entities[] = $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $object);
        }

        return new ArrayCollection($entities);
    }

    /**
     * Loads an entity by a list of field criteria.
     *
     * @param array       $criteria The criteria by which to load the entity.
     * @param object|null $entity   The entity to load the data into. If not specified, a new entity is created.
     * @param array|null  $assoc    The association that connects the entity to load to another entity, if any.
     * @param int|null    $limit    Limit number of results.
     * @param array|null  $orderBy  Criteria to order by.
     *
     * @return object|null The loaded and managed entity instance or NULL if the entity can not be found.
     *
     * @todo Check identity map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load(array $criteria,
                         $entity = null,
                         $assoc = null,
                         $limit = null,
                         array $orderBy = null)
    {
        // TODO: Implement load() method.
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

//
    }

    public function loadById(array $identifiers, $entity = null)
    {
        $finderClass = $this->metadata->getFinderClass();
        /** @var Finder $finder */
        $finder = new $finderClass($this->manager);
        $body   = $finder->find($this->client, $this->metadata, $identifiers);

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
     * @return array
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
     * @return array
     */
    public function getOneToManyCollection(array $assoc, $sourceEntity, $limit = null, $offset = null)
    {
        $targetClass = $assoc['target'];
        /** @var EntityMetadata $targetMetadata */
        $targetMetadata = $this->manager->getClassMetadata($targetClass);

        if ($this->metadata->isIdentifierComposite) {
            throw new \BadMethodCallException(__METHOD__ . ' on composite reference is not supported');
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
}
