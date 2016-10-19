<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection;

interface EntityPersister
{
    /**
     * @return ApiMetadata
     */
    public function getClassMetadata();

    /**
     * @return CrudsApiInterface
     */
    public function getCrudsApi();

    /**
     * Updates a managed entity. The entity is updated according to its current changeset
     * in the running UnitOfWork. If there is no changeset, nothing is updated.
     *
     * @param object $entity The entity to update.
     *
     * @return void
     */
    public function update($entity);

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
    public function delete($entity);

    /**
     * Count entities (optionally filtered by a criteria)
     *
     * @param  array|\Doctrine\Common\Collections\Criteria $criteria
     *
     * @return int
     */
    public function count($criteria = []);

    /**
     * Loads an entity by identifier.
     *
     * @param array       $identifier The entity identifier.
     * @param object|null $entity     The entity to load the data into. If not specified, a new entity is created.
     *
     * @return object The loaded and managed entity instance or NULL if the entity can not be found.
     */
    public function loadById(array $identifier, $entity = null);

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
    public function loadOneToOneEntity(array $assoc, $sourceEntity, array $identifier = []);

    /**
     * Refreshes a managed entity.
     *
     * @param array  $id         The identifier of the entity as an associative array from
     *                           column or field names to values.
     * @param object $entity     The entity to refresh.
     *                           or NULL if no specific lock mode should be used
     *                           for refreshing the managed entity.
     *
     * @return void
     */
    public function refresh(array $id, $entity);

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
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null);

    /**
     * Loads a collection of entities in a one-to-many association.
     *
     * @param array                  $assoc
     * @param object                 $sourceEntity
     * @param AbstractLazyCollection $collection The collection to load/fill.
     *
     * @return Collection
     */
    public function loadOneToManyCollection(array $assoc, $sourceEntity, AbstractLazyCollection $collection);


    /**
     * Returns an array with (sliced or full list) of elements in the specified collection.
     *
     * @param array    $assoc
     * @param object   $sourceEntity
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return Collection
     */
    public function getOneToManyCollection(array $assoc, $sourceEntity, $limit = null, $offset = null);


    /**
     * @param array  $mapping
     * @param object $sourceEntity
     *
     * @param array  $identifiers
     *
     * @return object
     */
    public function getToOneEntity(array $mapping, $sourceEntity, array $identifiers);
}

