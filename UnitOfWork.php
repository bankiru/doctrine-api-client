<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Cache\ApiEntityCache;
use Bankiru\Api\Doctrine\Cache\EntityCacheAwareInterface;
use Bankiru\Api\Doctrine\Cache\LoggingCache;
use Bankiru\Api\Doctrine\Cache\VoidEntityCache;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Hydration\EntityHydrator;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Persister\ApiPersister;
use Bankiru\Api\Doctrine\Persister\CollectionPersister;
use Bankiru\Api\Doctrine\Persister\EntityPersister;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Bankiru\Api\Doctrine\Utility\IdentifierFlattener;
use Bankiru\Api\Doctrine\Utility\ReflectionPropertiesGetter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\NotifyPropertyChanged;
use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\Common\Persistence\ObjectManagerAware;
use Doctrine\Common\PropertyChangedListener;
use Doctrine\Common\Proxy\Proxy;

class UnitOfWork implements PropertyChangedListener
{
    /**
     * An entity is in MANAGED state when its persistence is managed by an EntityManager.
     */
    const STATE_MANAGED = 1;
    /**
     * An entity is new if it has just been instantiated (i.e. using the "new" operator)
     * and is not (yet) managed by an EntityManager.
     */
    const STATE_NEW = 2;
    /**
     * A detached entity is an instance with persistent state and identity that is not
     * (or no longer) associated with an EntityManager (and a UnitOfWork).
     */
    const STATE_DETACHED = 3;
    /**
     * A removed entity instance is an instance with a persistent identity,
     * associated with an EntityManager, whose persistent state will be deleted
     * on commit.
     */
    const STATE_REMOVED = 4;

    /**
     * The (cached) states of any known entities.
     * Keys are object ids (spl_object_hash).
     *
     * @var array
     */
    private $entityStates = [];

    /** @var  EntityManager */
    private $manager;
    /** @var EntityPersister[] */
    private $persisters = [];
    /** @var CollectionPersister[] */
    private $collectionPersisters = [];
    /** @var  array */
    private $entityIdentifiers = [];
    /** @var  object[][] */
    private $identityMap = [];
    /** @var IdentifierFlattener */
    private $identifierFlattener;
    /** @var  array */
    private $originalEntityData = [];
    /** @var  array */
    private $entityDeletions = [];
    /** @var  array */
    private $entityChangeSets = [];
    /** @var  array */
    private $entityInsertions = [];
    /** @var  array */
    private $entityUpdates = [];
    /** @var  array */
    private $readOnlyObjects = [];
    /** @var  array */
    private $scheduledForSynchronization = [];
    /** @var  array */
    private $orphanRemovals = [];
    /** @var  ApiCollection[] */
    private $collectionDeletions = [];
    /** @var  array */
    private $extraUpdates = [];
    /** @var  ApiCollection[] */
    private $collectionUpdates = [];
    /** @var  ApiCollection[] */
    private $visitedCollections = [];
    /** @var ReflectionPropertiesGetter */
    private $reflectionPropertiesGetter;

    /**
     * UnitOfWork constructor.
     *
     * @param EntityManager $manager
     */
    public function __construct(EntityManager $manager)
    {
        $this->manager                    = $manager;
        $this->identifierFlattener        = new IdentifierFlattener($this->manager);
        $this->reflectionPropertiesGetter = new ReflectionPropertiesGetter(new RuntimeReflectionService());
    }

    /**
     * @param $className
     *
     * @return EntityPersister
     */
    public function getEntityPersister($className)
    {
        if (!array_key_exists($className, $this->persisters)) {
            /** @var ApiMetadata $classMetadata */
            $classMetadata = $this->manager->getClassMetadata($className);

            $api = $this->createApi($classMetadata);

            if ($api instanceof EntityCacheAwareInterface) {
                $api->setEntityCache($this->createEntityCache($classMetadata));
            }

            $this->persisters[$className] = new ApiPersister($this->manager, $api);
        }

        return $this->persisters[$className];
    }

    /**
     * Checks whether an entity is registered in the identity map of this UnitOfWork.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isInIdentityMap($entity)
    {
        $oid = spl_object_hash($entity);

        if (!isset($this->entityIdentifiers[$oid])) {
            return false;
        }

        /** @var EntityMetadata $classMetadata */
        $classMetadata = $this->manager->getClassMetadata(get_class($entity));
        $idHash        = implode(' ', $this->entityIdentifiers[$oid]);

        if ($idHash === '') {
            return false;
        }

        return isset($this->identityMap[$classMetadata->rootEntityName][$idHash]);
    }

    /**
     * Gets the identifier of an entity.
     * The returned value is always an array of identifier values. If the entity
     * has a composite identifier then the identifier values are in the same
     * order as the identifier field names as returned by ClassMetadata#getIdentifierFieldNames().
     *
     * @param object $entity
     *
     * @return array The identifier values.
     */
    public function getEntityIdentifier($entity)
    {
        return $this->entityIdentifiers[spl_object_hash($entity)];
    }

    /**
     * @param             $className
     * @param \stdClass   $data
     *
     * @return ObjectManagerAware|object
     * @throws MappingException
     */
    public function getOrCreateEntity($className, \stdClass $data)
    {
        /** @var EntityMetadata $class */
        $class    = $this->manager->getClassMetadata($className);
        $hydrator = new EntityHydrator($this->manager, $class);

        $tmpEntity = $hydrator->hydarate($data);

        $id     = $this->identifierFlattener->flattenIdentifier($class, $class->getIdentifierValues($tmpEntity));
        $idHash = implode(' ', $id);

        $overrideLocalValues = false;
        if (isset($this->identityMap[$class->rootEntityName][$idHash])) {
            $entity = $this->identityMap[$class->rootEntityName][$idHash];
            $oid    = spl_object_hash($entity);

            if ($entity instanceof Proxy && !$entity->__isInitialized()) {
                $entity->__setInitialized(true);

                $overrideLocalValues            = true;
                $this->originalEntityData[$oid] = $data;

                if ($entity instanceof NotifyPropertyChanged) {
                    $entity->addPropertyChangedListener($this);
                }
            }
        } else {
            $entity                                             = $this->newInstance($class);
            $oid                                                = spl_object_hash($entity);
            $this->entityIdentifiers[$oid]                      = $id;
            $this->entityStates[$oid]                           = self::STATE_MANAGED;
            $this->originalEntityData[$oid]                     = $data;
            $this->identityMap[$class->rootEntityName][$idHash] = $entity;
            if ($entity instanceof NotifyPropertyChanged) {
                $entity->addPropertyChangedListener($this);
            }
            $overrideLocalValues = true;
        }

        if (!$overrideLocalValues) {
            return $entity;
        }

        $entity = $hydrator->hydarate($data, $entity);

        return $entity;
    }

    /**
     * INTERNAL:
     * Registers an entity as managed.
     *
     * @param object         $entity The entity.
     * @param array          $id     The identifier values.
     * @param \stdClass|null $data   The original entity data.
     *
     * @return void
     */
    public function registerManaged($entity, array $id, \stdClass $data = null)
    {
        $oid = spl_object_hash($entity);

        $this->entityIdentifiers[$oid]  = $id;
        $this->entityStates[$oid]       = self::STATE_MANAGED;
        $this->originalEntityData[$oid] = $data;

        $this->addToIdentityMap($entity);

        if ($entity instanceof NotifyPropertyChanged && (!$entity instanceof Proxy || $entity->__isInitialized())) {
            $entity->addPropertyChangedListener($this);
        }
    }

    /**
     * INTERNAL:
     * Registers an entity in the identity map.
     * Note that entities in a hierarchy are registered with the class name of
     * the root entity.
     *
     * @ignore
     *
     * @param object $entity The entity to register.
     *
     * @return boolean TRUE if the registration was successful, FALSE if the identity of
     *                 the entity in question is already managed.
     *
     */
    public function addToIdentityMap($entity)
    {
        /** @var EntityMetadata $classMetadata */
        $classMetadata = $this->manager->getClassMetadata(get_class($entity));
        $idHash        = implode(' ', $this->entityIdentifiers[spl_object_hash($entity)]);

        if ($idHash === '') {
            throw new \InvalidArgumentException('Entitty does not have valid identifiers to be stored at identity map');
        }

        $className = $classMetadata->rootEntityName;

        if (isset($this->identityMap[$className][$idHash])) {
            return false;
        }

        $this->identityMap[$className][$idHash] = $entity;

        return true;
    }

    /**
     * Gets the identity map of the UnitOfWork.
     *
     * @return array
     */
    public function getIdentityMap()
    {
        return $this->identityMap;
    }

    /**
     * Gets the original data of an entity. The original data is the data that was
     * present at the time the entity was reconstituted from the database.
     *
     * @param object $entity
     *
     * @return array
     */
    public function getOriginalEntityData($entity)
    {
        $oid = spl_object_hash($entity);

        if (isset($this->originalEntityData[$oid])) {
            return $this->originalEntityData[$oid];
        }

        return [];
    }

    /**
     * INTERNAL:
     * Checks whether an identifier hash exists in the identity map.
     *
     * @ignore
     *
     * @param string $idHash
     * @param string $rootClassName
     *
     * @return boolean
     */
    public function containsIdHash($idHash, $rootClassName)
    {
        return isset($this->identityMap[$rootClassName][$idHash]);
    }

    /**
     * INTERNAL:
     * Gets an entity in the identity map by its identifier hash.
     *
     * @ignore
     *
     * @param string $idHash
     * @param string $rootClassName
     *
     * @return object
     */
    public function getByIdHash($idHash, $rootClassName)
    {
        return $this->identityMap[$rootClassName][$idHash];
    }

    /**
     * INTERNAL:
     * Tries to get an entity by its identifier hash. If no entity is found for
     * the given hash, FALSE is returned.
     *
     * @ignore
     *
     * @param mixed  $idHash (must be possible to cast it to string)
     * @param string $rootClassName
     *
     * @return object|bool The found entity or FALSE.
     */
    public function tryGetByIdHash($idHash, $rootClassName)
    {
        $stringIdHash = (string)$idHash;

        if (isset($this->identityMap[$rootClassName][$stringIdHash])) {
            return $this->identityMap[$rootClassName][$stringIdHash];
        }

        return false;
    }

    /**
     * Gets the state of an entity with regard to the current unit of work.
     *
     * @param object   $entity
     * @param int|null $assume The state to assume if the state is not yet known (not MANAGED or REMOVED).
     *                         This parameter can be set to improve performance of entity state detection
     *                         by potentially avoiding a database lookup if the distinction between NEW and DETACHED
     *                         is either known or does not matter for the caller of the method.
     *
     * @return int The entity state.
     */
    public function getEntityState($entity, $assume = null)
    {
        $oid = spl_object_hash($entity);
        if (isset($this->entityStates[$oid])) {
            return $this->entityStates[$oid];
        }
        if ($assume !== null) {
            return $assume;
        }
        // State can only be NEW or DETACHED, because MANAGED/REMOVED states are known.
        // Note that you can not remember the NEW or DETACHED state in _entityStates since
        // the UoW does not hold references to such objects and the object hash can be reused.
        // More generally because the state may "change" between NEW/DETACHED without the UoW being aware of it.
        $class = $this->manager->getClassMetadata(get_class($entity));
        $id    = $class->getIdentifierValues($entity);
        if (!$id) {
            return self::STATE_NEW;
        }

        return self::STATE_DETACHED;
    }

    /**
     * Tries to find an entity with the given identifier in the identity map of
     * this UnitOfWork.
     *
     * @param mixed  $id            The entity identifier to look for.
     * @param string $rootClassName The name of the root class of the mapped entity hierarchy.
     *
     * @return object|bool Returns the entity with the specified identifier if it exists in
     *                     this UnitOfWork, FALSE otherwise.
     */
    public function tryGetById($id, $rootClassName)
    {
        /** @var EntityMetadata $metadata */
        $metadata = $this->manager->getClassMetadata($rootClassName);
        $idHash   = implode(' ', (array)$this->identifierFlattener->flattenIdentifier($metadata, $id));

        if (isset($this->identityMap[$rootClassName][$idHash])) {
            return $this->identityMap[$rootClassName][$idHash];
        }

        return false;
    }

    /**
     * Notifies this UnitOfWork of a property change in an entity.
     *
     * @param object $entity       The entity that owns the property.
     * @param string $propertyName The name of the property that changed.
     * @param mixed  $oldValue     The old value of the property.
     * @param mixed  $newValue     The new value of the property.
     *
     * @return void
     */
    public function propertyChanged($entity, $propertyName, $oldValue, $newValue)
    {
        $oid          = spl_object_hash($entity);
        $class        = $this->manager->getClassMetadata(get_class($entity));
        $isAssocField = $class->hasAssociation($propertyName);
        if (!$isAssocField && !$class->hasField($propertyName)) {
            return; // ignore non-persistent fields
        }
        // Update changeset and mark entity for synchronization
        $this->entityChangeSets[$oid][$propertyName] = [$oldValue, $newValue];
        if (!isset($this->scheduledForSynchronization[$class->getRootEntityName()][$oid])) {
            $this->scheduleForDirtyCheck($entity);
        }
    }

    /**
     * Persists an entity as part of the current unit of work.
     *
     * @param object $entity The entity to persist.
     *
     * @return void
     */
    public function persist($entity)
    {
        $visited = [];
        $this->doPersist($entity, $visited);
    }

    /**
     * @param ApiMetadata $class
     * @param             $entity
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function recomputeSingleEntityChangeSet(ApiMetadata $class, $entity)
    {
        $oid = spl_object_hash($entity);
        if (!isset($this->entityStates[$oid]) || $this->entityStates[$oid] != self::STATE_MANAGED) {
            throw new \InvalidArgumentException('Entity is not managed');
        }

        $actualData = [];
        foreach ($class->getReflectionProperties() as $name => $refProp) {
            if (!$class->isIdentifier($name) && !$class->isCollectionValuedAssociation($name)) {
                $actualData[$name] = $refProp->getValue($entity);
            }
        }
        if (!isset($this->originalEntityData[$oid])) {
            throw new \RuntimeException(
                'Cannot call recomputeSingleEntityChangeSet before computeChangeSet on an entity.'
            );
        }
        $originalData = $this->originalEntityData[$oid];
        $changeSet    = [];
        foreach ($actualData as $propName => $actualValue) {
            $orgValue = isset($originalData[$propName]) ? $originalData[$propName] : null;
            if ($orgValue !== $actualValue) {
                $changeSet[$propName] = [$orgValue, $actualValue];
            }
        }
        if ($changeSet) {
            if (isset($this->entityChangeSets[$oid])) {
                $this->entityChangeSets[$oid] = array_merge($this->entityChangeSets[$oid], $changeSet);
            } else {
                if (!isset($this->entityInsertions[$oid])) {
                    $this->entityChangeSets[$oid] = $changeSet;
                    $this->entityUpdates[$oid]    = $entity;
                }
            }
            $this->originalEntityData[$oid] = $actualData;
        }
    }

    /**
     * Schedules an entity for insertion into the database.
     * If the entity already has an identifier, it will be added to the identity map.
     *
     * @param object $entity The entity to schedule for insertion.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function scheduleForInsert($entity)
    {
        $oid = spl_object_hash($entity);
        if (isset($this->entityUpdates[$oid])) {
            throw new \InvalidArgumentException('Dirty entity cannot be scheduled for insertion');
        }
        if (isset($this->entityDeletions[$oid])) {
            throw new \InvalidArgumentException('Removed entity scheduled for insertion');
        }
        if (isset($this->originalEntityData[$oid]) && !isset($this->entityInsertions[$oid])) {
            throw new \InvalidArgumentException('Managed entity scheduled for insertion');
        }
        if (isset($this->entityInsertions[$oid])) {
            throw new \InvalidArgumentException('Entity scheduled for insertion twice');
        }
        $this->entityInsertions[$oid] = $entity;
        if (isset($this->entityIdentifiers[$oid])) {
            $this->addToIdentityMap($entity);
        }
        if ($entity instanceof NotifyPropertyChanged) {
            $entity->addPropertyChangedListener($this);
        }
    }

    /**
     * Checks whether an entity is scheduled for insertion.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isScheduledForInsert($entity)
    {
        return isset($this->entityInsertions[spl_object_hash($entity)]);
    }

    /**
     * Schedules an entity for being updated.
     *
     * @param object $entity The entity to schedule for being updated.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function scheduleForUpdate($entity)
    {
        $oid = spl_object_hash($entity);
        if (!isset($this->entityIdentifiers[$oid])) {
            throw new \InvalidArgumentException('Entity has no identity');
        }
        if (isset($this->entityDeletions[$oid])) {
            throw new \InvalidArgumentException('Entity is removed');
        }
        if (!isset($this->entityUpdates[$oid]) && !isset($this->entityInsertions[$oid])) {
            $this->entityUpdates[$oid] = $entity;
        }
    }

    /**
     * Checks whether an entity is registered as dirty in the unit of work.
     * Note: Is not very useful currently as dirty entities are only registered
     * at commit time.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isScheduledForUpdate($entity)
    {
        return isset($this->entityUpdates[spl_object_hash($entity)]);
    }

    /**
     * Checks whether an entity is registered to be checked in the unit of work.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isScheduledForDirtyCheck($entity)
    {
        $rootEntityName = $this->manager->getClassMetadata(get_class($entity))->getRootEntityName();

        return isset($this->scheduledForSynchronization[$rootEntityName][spl_object_hash($entity)]);
    }

    /**
     * INTERNAL:
     * Schedules an entity for deletion.
     *
     * @param object $entity
     *
     * @return void
     */
    public function scheduleForDelete($entity)
    {
        $oid = spl_object_hash($entity);
        if (isset($this->entityInsertions[$oid])) {
            if ($this->isInIdentityMap($entity)) {
                $this->removeFromIdentityMap($entity);
            }
            unset($this->entityInsertions[$oid], $this->entityStates[$oid]);

            return; // entity has not been persisted yet, so nothing more to do.
        }
        if (!$this->isInIdentityMap($entity)) {
            return;
        }
        $this->removeFromIdentityMap($entity);
        unset($this->entityUpdates[$oid]);
        if (!isset($this->entityDeletions[$oid])) {
            $this->entityDeletions[$oid] = $entity;
            $this->entityStates[$oid]    = self::STATE_REMOVED;
        }
    }

    /**
     * Checks whether an entity is registered as removed/deleted with the unit
     * of work.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isScheduledForDelete($entity)
    {
        return isset($this->entityDeletions[spl_object_hash($entity)]);
    }

    /**
     * Checks whether an entity is scheduled for insertion, update or deletion.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isEntityScheduled($entity)
    {
        $oid = spl_object_hash($entity);

        return isset($this->entityInsertions[$oid])
               || isset($this->entityUpdates[$oid])
               || isset($this->entityDeletions[$oid]);
    }

    /**
     * INTERNAL:
     * Removes an entity from the identity map. This effectively detaches the
     * entity from the persistence management of Doctrine.
     *
     * @ignore
     *
     * @param object $entity
     *
     * @return boolean
     *
     * @throws \InvalidArgumentException
     */
    public function removeFromIdentityMap($entity)
    {
        $oid           = spl_object_hash($entity);
        $classMetadata = $this->manager->getClassMetadata(get_class($entity));
        $idHash        = implode(' ', $this->entityIdentifiers[$oid]);
        if ($idHash === '') {
            throw new \InvalidArgumentException('Entity has no identity');
        }
        $className = $classMetadata->getRootEntityName();
        if (isset($this->identityMap[$className][$idHash])) {
            unset($this->identityMap[$className][$idHash]);
            unset($this->readOnlyObjects[$oid]);

            //$this->entityStates[$oid] = self::STATE_DETACHED;
            return true;
        }

        return false;
    }

    /**
     * Commits the UnitOfWork, executing all operations that have been postponed
     * up to this point. The state of all managed entities will be synchronized with
     * the database.
     *
     * The operations are executed in the following order:
     *
     * 1) All entity insertions
     * 2) All entity updates
     * 3) All collection deletions
     * 4) All collection updates
     * 5) All entity deletions
     *
     * @param null|object|array $entity
     *
     * @return void
     *
     * @throws \Exception
     */
    public function commit($entity = null)
    {
        // Compute changes done since last commit.
        if ($entity === null) {
            $this->computeChangeSets();
        } elseif (is_object($entity)) {
            $this->computeSingleEntityChangeSet($entity);
        } elseif (is_array($entity)) {
            foreach ((array)$entity as $object) {
                $this->computeSingleEntityChangeSet($object);
            }
        }
        if (!($this->entityInsertions ||
              $this->entityDeletions ||
              $this->entityUpdates ||
              $this->collectionUpdates ||
              $this->collectionDeletions ||
              $this->orphanRemovals)
        ) {
            return; // Nothing to do.
        }
        if ($this->orphanRemovals) {
            foreach ($this->orphanRemovals as $orphan) {
                $this->remove($orphan);
            }
        }
        // Now we need a commit order to maintain referential integrity
        $commitOrder = $this->getCommitOrder();

        // Collection deletions (deletions of complete collections)
        // foreach ($this->collectionDeletions as $collectionToDelete) {
        //       //fixme: collection mutations
        //       $this->getCollectionPersister($collectionToDelete->getMapping())->delete($collectionToDelete);
        // }
        if ($this->entityInsertions) {
            foreach ($commitOrder as $class) {
                $this->executeInserts($class);
            }
        }
        if ($this->entityUpdates) {
            foreach ($commitOrder as $class) {
                $this->executeUpdates($class);
            }
        }
        // Extra updates that were requested by persisters.
        if ($this->extraUpdates) {
            $this->executeExtraUpdates();
        }
        // Collection updates (deleteRows, updateRows, insertRows)
        foreach ($this->collectionUpdates as $collectionToUpdate) {
            //fixme: decide what to do with collection mutation if API does not support this
            //$this->getCollectionPersister($collectionToUpdate->getMapping())->update($collectionToUpdate);
        }
        // Entity deletions come last and need to be in reverse commit order
        if ($this->entityDeletions) {
            for ($count = count($commitOrder), $i = $count - 1; $i >= 0 && $this->entityDeletions; --$i) {
                $this->executeDeletions($commitOrder[$i]);
            }
        }

        // Take new snapshots from visited collections
        foreach ($this->visitedCollections as $coll) {
            $coll->takeSnapshot();
        }

        // Clear up
        $this->entityInsertions =
        $this->entityUpdates =
        $this->entityDeletions =
        $this->extraUpdates =
        $this->entityChangeSets =
        $this->collectionUpdates =
        $this->collectionDeletions =
        $this->visitedCollections =
        $this->scheduledForSynchronization =
        $this->orphanRemovals = [];
    }

    /**
     * Gets the changeset for an entity.
     *
     * @param object $entity
     *
     * @return array
     */
    public function & getEntityChangeSet($entity)
    {
        $oid  = spl_object_hash($entity);
        $data = [];
        if (!isset($this->entityChangeSets[$oid])) {
            return $data;
        }

        return $this->entityChangeSets[$oid];
    }

    /**
     * Computes the changes that happened to a single entity.
     *
     * Modifies/populates the following properties:
     *
     * {@link _originalEntityData}
     * If the entity is NEW or MANAGED but not yet fully persisted (only has an id)
     * then it was not fetched from the database and therefore we have no original
     * entity data yet. All of the current entity data is stored as the original entity data.
     *
     * {@link _entityChangeSets}
     * The changes detected on all properties of the entity are stored there.
     * A change is a tuple array where the first entry is the old value and the second
     * entry is the new value of the property. Changesets are used by persisters
     * to INSERT/UPDATE the persistent entity state.
     *
     * {@link _entityUpdates}
     * If the entity is already fully MANAGED (has been fetched from the database before)
     * and any changes to its properties are detected, then a reference to the entity is stored
     * there to mark it for an update.
     *
     * {@link _collectionDeletions}
     * If a PersistentCollection has been de-referenced in a fully MANAGED entity,
     * then this collection is marked for deletion.
     *
     * @ignore
     *
     * @internal Don't call from the outside.
     *
     * @param ApiMetadata $class  The class descriptor of the entity.
     * @param object      $entity The entity for which to compute the changes.
     *
     * @return void
     */
    public function computeChangeSet(ApiMetadata $class, $entity)
    {
        $oid = spl_object_hash($entity);
        if (isset($this->readOnlyObjects[$oid])) {
            return;
        }
        //        if ( ! $class->isInheritanceTypeNone()) {
        //            $class = $this->em->getClassMetadata(get_class($entity));
        //        }
        //        $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::preFlush) & ~ListenersInvoker::INVOKE_MANAGER;
        //        if ($invoke !== ListenersInvoker::INVOKE_NONE) {
        //            $this->listenersInvoker->invoke($class, Events::preFlush, $entity, new PreFlushEventArgs($this->em), $invoke);
        //        }
        $actualData = [];
        foreach ($class->getReflectionProperties() as $name => $refProp) {
            $value = $refProp->getValue($entity);
            if ($class->isCollectionValuedAssociation($name) && $value !== null) {
                if ($value instanceof ApiCollection) {
                    if ($value->getOwner() === $entity) {
                        continue;
                    }
                    $value = new ArrayCollection($value->getValues());
                }
                // If $value is not a Collection then use an ArrayCollection.
                if (!$value instanceof Collection) {
                    $value = new ArrayCollection($value);
                }
                $assoc = $class->getAssociationMapping($name);
                // Inject PersistentCollection
                $value = new ApiCollection(
                    $this->manager,
                    $this->manager->getClassMetadata($assoc['target']),
                    $value
                );
                $value->setOwner($entity, $assoc);
                $value->setDirty(!$value->isEmpty());
                $class->getReflectionProperty($name)->setValue($entity, $value);
                $actualData[$name] = $value;
                continue;
            }
            if (!$class->isIdentifier($name)) {
                $actualData[$name] = $value;
            }
        }
        if (!isset($this->originalEntityData[$oid])) {
            // Entity is either NEW or MANAGED but not yet fully persisted (only has an id).
            // These result in an INSERT.
            $this->originalEntityData[$oid] = $actualData;
            $changeSet                      = [];
            foreach ($actualData as $propName => $actualValue) {
                if (!$class->hasAssociation($propName)) {
                    $changeSet[$propName] = [null, $actualValue];
                    continue;
                }
                $assoc = $class->getAssociationMapping($propName);
                if ($assoc['isOwningSide'] && $assoc['type'] & ApiMetadata::TO_ONE) {
                    $changeSet[$propName] = [null, $actualValue];
                }
            }
            $this->entityChangeSets[$oid] = $changeSet;
        } else {
            // Entity is "fully" MANAGED: it was already fully persisted before
            // and we have a copy of the original data
            $originalData           = $this->originalEntityData[$oid];
            $isChangeTrackingNotify = false;
            $changeSet              = ($isChangeTrackingNotify && isset($this->entityChangeSets[$oid]))
                ? $this->entityChangeSets[$oid]
                : [];
            foreach ($actualData as $propName => $actualValue) {
                // skip field, its a partially omitted one!
                if (!(isset($originalData[$propName]) || array_key_exists($propName, $originalData))) {
                    continue;
                }
                $orgValue = $originalData[$propName];
                // skip if value haven't changed
                if ($orgValue === $actualValue) {
                    continue;
                }
                // if regular field
                if (!$class->hasAssociation($propName)) {
                    if ($isChangeTrackingNotify) {
                        continue;
                    }
                    $changeSet[$propName] = [$orgValue, $actualValue];
                    continue;
                }
                $assoc = $class->getAssociationMapping($propName);
                // Persistent collection was exchanged with the "originally"
                // created one. This can only mean it was cloned and replaced
                // on another entity.
                if ($actualValue instanceof ApiCollection) {
                    $owner = $actualValue->getOwner();
                    if ($owner === null) { // cloned
                        $actualValue->setOwner($entity, $assoc);
                    } else {
                        if ($owner !== $entity) { // no clone, we have to fix
                            if (!$actualValue->isInitialized()) {
                                $actualValue->initialize(); // we have to do this otherwise the cols share state
                            }
                            $newValue = clone $actualValue;
                            $newValue->setOwner($entity, $assoc);
                            $class->getReflectionProperty($propName)->setValue($entity, $newValue);
                        }
                    }
                }
                if ($orgValue instanceof ApiCollection) {
                    // A PersistentCollection was de-referenced, so delete it.
                    $coid = spl_object_hash($orgValue);
                    if (isset($this->collectionDeletions[$coid])) {
                        continue;
                    }
                    $this->collectionDeletions[$coid] = $orgValue;
                    $changeSet[$propName]             = $orgValue; // Signal changeset, to-many assocs will be ignored.
                    continue;
                }
                if ($assoc['type'] & ApiMetadata::TO_ONE) {
                    if ($assoc['isOwningSide']) {
                        $changeSet[$propName] = [$orgValue, $actualValue];
                    }
                    if ($orgValue !== null && $assoc['orphanRemoval']) {
                        $this->scheduleOrphanRemoval($orgValue);
                    }
                }
            }
            if ($changeSet) {
                $this->entityChangeSets[$oid]   = $changeSet;
                $this->originalEntityData[$oid] = $actualData;
                $this->entityUpdates[$oid]      = $entity;
            }
        }
        // Look for changes in associations of the entity
        foreach ($class->getAssociationMappings() as $field => $assoc) {
            if (($val = $class->getReflectionProperty($field)->getValue($entity)) === null) {
                continue;
            }
            $this->computeAssociationChanges($assoc, $val);
            if (!isset($this->entityChangeSets[$oid]) &&
                $assoc['isOwningSide'] &&
                $assoc['type'] == ApiMetadata::MANY_TO_MANY &&
                $val instanceof ApiCollection &&
                $val->isDirty()
            ) {
                $this->entityChangeSets[$oid]   = [];
                $this->originalEntityData[$oid] = $actualData;
                $this->entityUpdates[$oid]      = $entity;
            }
        }
    }

    /**
     * Computes all the changes that have been done to entities and collections
     * since the last commit and stores these changes in the _entityChangeSet map
     * temporarily for access by the persisters, until the UoW commit is finished.
     *
     * @return void
     */
    public function computeChangeSets()
    {
        // Compute changes for INSERTed entities first. This must always happen.
        $this->computeScheduleInsertsChangeSets();
        // Compute changes for other MANAGED entities. Change tracking policies take effect here.
        foreach ($this->identityMap as $className => $entities) {
            $class = $this->manager->getClassMetadata($className);
            // Skip class if instances are read-only
            if ($class->isReadOnly()) {
                continue;
            }
            // If change tracking is explicit or happens through notification, then only compute
            // changes on entities of that type that are explicitly marked for synchronization.
            switch (true) {
                case ($class->isChangeTrackingDeferredImplicit()):
                    $entitiesToProcess = $entities;
                    break;
                case (isset($this->scheduledForSynchronization[$className])):
                    $entitiesToProcess = $this->scheduledForSynchronization[$className];
                    break;
                default:
                    $entitiesToProcess = [];
            }
            foreach ($entitiesToProcess as $entity) {
                // Ignore uninitialized proxy objects
                if ($entity instanceof Proxy && !$entity->__isInitialized__) {
                    continue;
                }
                // Only MANAGED entities that are NOT SCHEDULED FOR INSERTION OR DELETION are processed here.
                $oid = spl_object_hash($entity);
                if (!isset($this->entityInsertions[$oid]) &&
                    !isset($this->entityDeletions[$oid]) &&
                    isset($this->entityStates[$oid])
                ) {
                    $this->computeChangeSet($class, $entity);
                }
            }
        }
    }

    /**
     * INTERNAL:
     * Schedules an orphaned entity for removal. The remove() operation will be
     * invoked on that entity at the beginning of the next commit of this
     * UnitOfWork.
     *
     * @ignore
     *
     * @param object $entity
     *
     * @return void
     */
    public function scheduleOrphanRemoval($entity)
    {
        $this->orphanRemovals[spl_object_hash($entity)] = $entity;
    }

    public function loadCollection(ApiCollection $collection)
    {
        $assoc     = $collection->getMapping();
        $persister = $this->getEntityPersister($assoc['target']);
        switch ($assoc['type']) {
            case ApiMetadata::ONE_TO_MANY:
                $persister->loadOneToManyCollection($assoc, $collection->getOwner(), $collection);
                break;
        }
        $collection->setInitialized(true);
    }

    public function getCollectionPersister($association)
    {
        $role = isset($association['cache'])
            ? $association['sourceEntity'] . '::' . $association['fieldName']
            : $association['type'];
        if (array_key_exists($role, $this->collectionPersisters)) {
            return $this->collectionPersisters[$role];
        }
        $this->collectionPersisters[$role] = new CollectionPersister($this->manager);

        return $this->collectionPersisters[$role];
    }

    public function scheduleCollectionDeletion(Collection $collection)
    {
    }

    public function cancelOrphanRemoval($value)
    {
    }

    /**
     * INTERNAL:
     * Sets a property value of the original data array of an entity.
     *
     * @ignore
     *
     * @param string $oid
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    public function setOriginalEntityProperty($oid, $property, $value)
    {
        if (!array_key_exists($oid, $this->originalEntityData)) {
            $this->originalEntityData[$oid] = new \stdClass();
        }

        $this->originalEntityData[$oid]->$property = $value;
    }

    public function scheduleExtraUpdate($entity, $changeset)
    {
        $oid         = spl_object_hash($entity);
        $extraUpdate = [$entity, $changeset];
        if (isset($this->extraUpdates[$oid])) {
            list(, $changeset2) = $this->extraUpdates[$oid];
            $extraUpdate = [$entity, $changeset + $changeset2];
        }
        $this->extraUpdates[$oid] = $extraUpdate;
    }

    /**
     * Refreshes the state of the given entity from the database, overwriting
     * any local, unpersisted changes.
     *
     * @param object $entity The entity to refresh.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the entity is not MANAGED.
     */
    public function refresh($entity)
    {
        $visited = [];
        $this->doRefresh($entity, $visited);
    }

    /**
     * Clears the UnitOfWork.
     *
     * @param string|null $entityName if given, only entities of this type will get detached.
     *
     * @return void
     */
    public function clear($entityName = null)
    {
        if ($entityName === null) {
            $this->identityMap =
            $this->entityIdentifiers =
            $this->originalEntityData =
            $this->entityChangeSets =
            $this->entityStates =
            $this->scheduledForSynchronization =
            $this->entityInsertions =
            $this->entityUpdates =
            $this->entityDeletions =
            $this->collectionDeletions =
            $this->collectionUpdates =
            $this->extraUpdates =
            $this->readOnlyObjects =
            $this->visitedCollections =
            $this->orphanRemovals = [];
        } else {
            $this->clearIdentityMapForEntityName($entityName);
            $this->clearEntityInsertionsForEntityName($entityName);
        }
    }

    /**
     * @param PersistentCollection $coll
     *
     * @return bool
     */
    public function isCollectionScheduledForDeletion(PersistentCollection $coll)
    {
        return isset($this->collectionDeletions[spl_object_hash($coll)]);
    }

    /**
     * Schedules an entity for dirty-checking at commit-time.
     *
     * @param object $entity The entity to schedule for dirty-checking.
     *
     * @return void
     *
     * @todo Rename: scheduleForSynchronization
     */
    public function scheduleForDirtyCheck($entity)
    {
        $rootClassName                                                               =
            $this->manager->getClassMetadata(get_class($entity))->getRootEntityName();
        $this->scheduledForSynchronization[$rootClassName][spl_object_hash($entity)] = $entity;
    }

    /**
     * Deletes an entity as part of the current unit of work.
     *
     * @param object $entity The entity to remove.
     *
     * @return void
     */
    public function remove($entity)
    {
        $visited = [];
        $this->doRemove($entity, $visited);
    }

    /**
     * Merges the state of the given detached entity into this UnitOfWork.
     *
     * @param object $entity
     *
     * @return object The managed copy of the entity.
     */
    public function merge($entity)
    {
        $visited = [];

        return $this->doMerge($entity, $visited);
    }

    /**
     * Detaches an entity from the persistence management. It's persistence will
     * no longer be managed by Doctrine.
     *
     * @param object $entity The entity to detach.
     *
     * @return void
     */
    public function detach($entity)
    {
        $visited = [];
        $this->doDetach($entity, $visited);
    }

    /**
     * @param ApiMetadata $class
     *
     * @return \Doctrine\Common\Persistence\ObjectManagerAware|object
     */
    private function newInstance(ApiMetadata $class)
    {
        $entity = $class->newInstance();

        if ($entity instanceof ObjectManagerAware) {
            $entity->injectObjectManager($this->manager, $class);
        }

        return $entity;
    }

    /**
     * @param ApiMetadata $classMetadata
     *
     * @return EntityDataCacheInterface
     */
    private function createEntityCache(ApiMetadata $classMetadata)
    {
        $configuration = $this->manager->getConfiguration()->getCacheConfiguration($classMetadata->getName());
        $cache         = new VoidEntityCache($classMetadata);
        if ($configuration->isEnabled() && $this->manager->getConfiguration()->getApiCache()) {
            $cache =
                new LoggingCache(
                    new ApiEntityCache(
                        $this->manager->getConfiguration()->getApiCache(),
                        $classMetadata,
                        $configuration
                    ),
                    $this->manager->getConfiguration()->getApiCacheLogger()
                );

            return $cache;
        }

        return $cache;
    }

    /**
     * @param ApiMetadata $classMetadata
     *
     * @return CrudsApiInterface
     */
    private function createApi(ApiMetadata $classMetadata)
    {
        $client = $this->manager->getConfiguration()->getRegistry()->get($classMetadata->getClientName());

        $api = $this->manager
            ->getConfiguration()
            ->getResolver()
            ->resolve($classMetadata->getApiName())
            ->createApi(
                $client,
                $classMetadata
            );

        return $api;
    }

    private function doPersist($entity, $visited)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }
        $visited[$oid] = $entity; // Mark visited
        $class         = $this->manager->getClassMetadata(get_class($entity));
        // We assume NEW, so DETACHED entities result in an exception on flush (constraint violation).
        // If we would detect DETACHED here we would throw an exception anyway with the same
        // consequences (not recoverable/programming error), so just assuming NEW here
        // lets us avoid some database lookups for entities with natural identifiers.
        $entityState = $this->getEntityState($entity, self::STATE_NEW);
        switch ($entityState) {
            case self::STATE_MANAGED:
                $this->scheduleForDirtyCheck($entity);
                break;
            case self::STATE_NEW:
                $this->persistNew($class, $entity);
                break;
            case self::STATE_REMOVED:
                // Entity becomes managed again
                unset($this->entityDeletions[$oid]);
                $this->addToIdentityMap($entity);
                $this->entityStates[$oid] = self::STATE_MANAGED;
                break;
            case self::STATE_DETACHED:
                // Can actually not happen right now since we assume STATE_NEW.
                throw new \InvalidArgumentException('Detached entity cannot be persisted');
            default:
                throw new \UnexpectedValueException("Unexpected entity state: $entityState." . self::objToStr($entity));
        }
        $this->cascadePersist($entity, $visited);
    }

    /**
     * Cascades the save operation to associated entities.
     *
     * @param object $entity
     * @param array  $visited
     *
     * @return void
     * @throws \InvalidArgumentException
     * @throws MappingException
     */
    private function cascadePersist($entity, array &$visited)
    {
        $class               = $this->manager->getClassMetadata(get_class($entity));
        $associationMappings = [];
        foreach ($class->getAssociationNames() as $name) {
            $assoc = $class->getAssociationMapping($name);
            if ($assoc['isCascadePersist']) {
                $associationMappings[$name] = $assoc;
            }
        }
        foreach ($associationMappings as $assoc) {
            $relatedEntities = $class->getReflectionProperty($assoc['field'])->getValue($entity);
            switch (true) {
                case ($relatedEntities instanceof ApiCollection):
                    // Unwrap so that foreach() does not initialize
                    $relatedEntities = $relatedEntities->unwrap();
                // break; is commented intentionally!
                case ($relatedEntities instanceof Collection):
                case (is_array($relatedEntities)):
                    if (($assoc['type'] & ApiMetadata::TO_MANY) <= 0) {
                        throw new \InvalidArgumentException('Invalid association for cascade');
                    }
                    foreach ($relatedEntities as $relatedEntity) {
                        $this->doPersist($relatedEntity, $visited);
                    }
                    break;
                case ($relatedEntities !== null):
                    if (!$relatedEntities instanceof $assoc['target']) {
                        throw new \InvalidArgumentException('Invalid association for cascade');
                    }
                    $this->doPersist($relatedEntities, $visited);
                    break;
                default:
                    // Do nothing
            }
        }
    }

    /**
     * @param ApiMetadata $class
     * @param object      $entity
     *
     * @return void
     */
    private function persistNew($class, $entity)
    {
        $oid = spl_object_hash($entity);
        //        $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::prePersist);
        //        if ($invoke !== ListenersInvoker::INVOKE_NONE) {
        //            $this->listenersInvoker->invoke($class, Events::prePersist, $entity, new LifecycleEventArgs($entity, $this->em), $invoke);
        //        }
        //        $idGen = $class->idGenerator;
        //        if ( ! $idGen->isPostInsertGenerator()) {
        //            $idValue = $idGen->generate($this->em, $entity);
        //            if ( ! $idGen instanceof \Doctrine\ORM\Id\AssignedGenerator) {
        //                $idValue = array($class->identifier[0] => $idValue);
        //                $class->setIdentifierValues($entity, $idValue);
        //            }
        //            $this->entityIdentifiers[$oid] = $idValue;
        //        }
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->scheduleForInsert($entity);
    }

    /**
     * Gets the commit order.
     *
     * @param array|null $entityChangeSet
     *
     * @return array
     */
    private function getCommitOrder(array $entityChangeSet = null)
    {
        if ($entityChangeSet === null) {
            $entityChangeSet = array_merge($this->entityInsertions, $this->entityUpdates, $this->entityDeletions);
        }
        $calc = $this->getCommitOrderCalculator();
        // See if there are any new classes in the changeset, that are not in the
        // commit order graph yet (don't have a node).
        // We have to inspect changeSet to be able to correctly build dependencies.
        // It is not possible to use IdentityMap here because post inserted ids
        // are not yet available.
        /** @var ApiMetadata[] $newNodes */
        $newNodes = [];
        foreach ((array)$entityChangeSet as $entity) {
            $class = $this->manager->getClassMetadata(get_class($entity));
            if ($calc->hasNode($class->getName())) {
                continue;
            }
            $calc->addNode($class->getName(), $class);
            $newNodes[] = $class;
        }
        // Calculate dependencies for new nodes
        while ($class = array_pop($newNodes)) {
            foreach ($class->getAssociationMappings() as $assoc) {
                if (!($assoc['isOwningSide'] && $assoc['type'] & ApiMetadata::TO_ONE)) {
                    continue;
                }
                $targetClass = $this->manager->getClassMetadata($assoc['target']);
                if (!$calc->hasNode($targetClass->getName())) {
                    $calc->addNode($targetClass->getName(), $targetClass);
                    $newNodes[] = $targetClass;
                }
                $calc->addDependency($targetClass->getName(), $class->name, (int)empty($assoc['nullable']));
                // If the target class has mapped subclasses, these share the same dependency.
                if (!$targetClass->getSubclasses()) {
                    continue;
                }
                foreach ($targetClass->getSubclasses() as $subClassName) {
                    $targetSubClass = $this->manager->getClassMetadata($subClassName);
                    if (!$calc->hasNode($subClassName)) {
                        $calc->addNode($targetSubClass->name, $targetSubClass);
                        $newNodes[] = $targetSubClass;
                    }
                    $calc->addDependency($targetSubClass->name, $class->name, 1);
                }
            }
        }

        return $calc->sort();
    }

    /**
     * Helper method to show an object as string.
     *
     * @param object $obj
     *
     * @return string
     */
    private static function objToStr($obj)
    {
        return method_exists($obj, '__toString') ? (string)$obj : get_class($obj) . '@' . spl_object_hash($obj);
    }

    private function getCommitOrderCalculator()
    {
        return new Utility\CommitOrderCalculator();
    }

    /**
     * Only flushes the given entity according to a ruleset that keeps the UoW consistent.
     *
     * 1. All entities scheduled for insertion, (orphan) removals and changes in collections are processed as well!
     * 2. Read Only entities are skipped.
     * 3. Proxies are skipped.
     * 4. Only if entity is properly managed.
     *
     * @param object $entity
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function computeSingleEntityChangeSet($entity)
    {
        $state = $this->getEntityState($entity);
        if ($state !== self::STATE_MANAGED && $state !== self::STATE_REMOVED) {
            throw new \InvalidArgumentException(
                "Entity has to be managed or scheduled for removal for single computation " . self::objToStr($entity)
            );
        }
        $class = $this->manager->getClassMetadata(get_class($entity));
        // Compute changes for INSERTed entities first. This must always happen even in this case.
        $this->computeScheduleInsertsChangeSets();
        if ($class->isReadOnly()) {
            return;
        }
        // Ignore uninitialized proxy objects
        if ($entity instanceof Proxy && !$entity->__isInitialized__) {
            return;
        }
        // Only MANAGED entities that are NOT SCHEDULED FOR INSERTION OR DELETION are processed here.
        $oid = spl_object_hash($entity);
        if (!isset($this->entityInsertions[$oid]) &&
            !isset($this->entityDeletions[$oid]) &&
            isset($this->entityStates[$oid])
        ) {
            $this->computeChangeSet($class, $entity);
        }
    }

    /**
     * Computes the changesets of all entities scheduled for insertion.
     *
     * @return void
     */
    private function computeScheduleInsertsChangeSets()
    {
        foreach ($this->entityInsertions as $entity) {
            $class = $this->manager->getClassMetadata(get_class($entity));
            $this->computeChangeSet($class, $entity);
        }
    }

    /**
     * Computes the changes of an association.
     *
     * @param array $assoc The association mapping.
     * @param mixed $value The value of the association.
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     *
     * @return void
     */
    private function computeAssociationChanges($assoc, $value)
    {
        if ($value instanceof Proxy && !$value->__isInitialized__) {
            return;
        }
        if ($value instanceof ApiCollection && $value->isDirty()) {
            $coid                            = spl_object_hash($value);
            $this->collectionUpdates[$coid]  = $value;
            $this->visitedCollections[$coid] = $value;
        }
        // Look through the entities, and in any of their associations,
        // for transient (new) entities, recursively. ("Persistence by reachability")
        // Unwrap. Uninitialized collections will simply be empty.
        $unwrappedValue  = ($assoc['type'] & ApiMetadata::TO_ONE) ? [$value] : $value->unwrap();
        $targetClass     = $this->manager->getClassMetadata($assoc['target']);
        $targetClassName = $targetClass->getName();
        foreach ($unwrappedValue as $key => $entry) {
            if (!($entry instanceof $targetClassName)) {
                throw new \InvalidArgumentException('Invalid association');
            }
            $state = $this->getEntityState($entry, self::STATE_NEW);
            if (!($entry instanceof $assoc['target'])) {
                throw new \UnexpectedValueException('Unexpected association');
            }
            switch ($state) {
                case self::STATE_NEW:
                    if (!$assoc['isCascadePersist']) {
                        throw new \InvalidArgumentException('New entity through relationship');
                    }
                    $this->persistNew($targetClass, $entry);
                    $this->computeChangeSet($targetClass, $entry);
                    break;
                case self::STATE_REMOVED:
                    // Consume the $value as array (it's either an array or an ArrayAccess)
                    // and remove the element from Collection.
                    if ($assoc['type'] & ApiMetadata::TO_MANY) {
                        unset($value[$key]);
                    }
                    break;
                case self::STATE_DETACHED:
                    // Can actually not happen right now as we assume STATE_NEW,
                    // so the exception will be raised from the DBAL layer (constraint violation).
                    throw new \InvalidArgumentException('Detached entity through relationship');
                    break;
                default:
                    // MANAGED associated entities are already taken into account
                    // during changeset calculation anyway, since they are in the identity map.
            }
        }
    }

    private function executeInserts(ApiMetadata $class)
    {
        $className = $class->getName();
        $persister = $this->getEntityPersister($className);
        foreach ($this->entityInsertions as $oid => $entity) {
            if ($this->manager->getClassMetadata(get_class($entity))->getName() !== $className) {
                continue;
            }
            $persister->pushNewEntity($entity);
            unset($this->entityInsertions[$oid]);
        }
        $postInsertIds = $persister->flushNewEntities();
        if ($postInsertIds) {
            // Persister returned post-insert IDs
            foreach ($postInsertIds as $postInsertId) {
                $id      = $postInsertId['generatedId'];
                $entity  = $postInsertId['entity'];
                $oid     = spl_object_hash($entity);
                $idField = $class->getIdentifierFieldNames()[0];
                $class->getReflectionProperty($idField)->setValue($entity, $id);
                $this->entityIdentifiers[$oid]            = [$idField => $id];
                $this->entityStates[$oid]                 = self::STATE_MANAGED;
                $this->originalEntityData[$oid][$idField] = $id;
                $this->addToIdentityMap($entity);
            }
        }
    }

    private function executeUpdates($class)
    {
        $className = $class->name;
        $persister = $this->getEntityPersister($className);
        foreach ($this->entityUpdates as $oid => $entity) {
            if ($this->manager->getClassMetadata(get_class($entity))->getName() !== $className) {
                continue;
            }
            $this->recomputeSingleEntityChangeSet($class, $entity);

            if (!empty($this->entityChangeSets[$oid])) {
                $persister->update($entity);
            }
            unset($this->entityUpdates[$oid]);
        }
    }

    /**
     * Executes a refresh operation on an entity.
     *
     * @param object $entity  The entity to refresh.
     * @param array  $visited The already visited entities during cascades.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the entity is not MANAGED.
     */
    private function doRefresh($entity, array &$visited)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }
        $visited[$oid] = $entity; // mark visited
        $class         = $this->manager->getClassMetadata(get_class($entity));
        if ($this->getEntityState($entity) !== self::STATE_MANAGED) {
            throw new \InvalidArgumentException('Entity not managed');
        }
        $this->getEntityPersister($class->getName())->refresh(
            array_combine($class->getIdentifierFieldNames(), $this->entityIdentifiers[$oid]),
            $entity
        );
        $this->cascadeRefresh($entity, $visited);
    }

    /**
     * Cascades a refresh operation to associated entities.
     *
     * @param object $entity
     * @param array  $visited
     *
     * @return void
     */
    private function cascadeRefresh($entity, array &$visited)
    {
        $class               = $this->manager->getClassMetadata(get_class($entity));
        $associationMappings = array_filter(
            $class->getAssociationMappings(),
            function ($assoc) {
                return $assoc['isCascadeRefresh'];
            }
        );
        foreach ($associationMappings as $assoc) {
            $relatedEntities = $class->getReflectionProperty($assoc['fieldName'])->getValue($entity);
            switch (true) {
                case ($relatedEntities instanceof ApiCollection):
                    // Unwrap so that foreach() does not initialize
                    $relatedEntities = $relatedEntities->unwrap();
                // break; is commented intentionally!
                case ($relatedEntities instanceof Collection):
                case (is_array($relatedEntities)):
                    foreach ($relatedEntities as $relatedEntity) {
                        $this->doRefresh($relatedEntity, $visited);
                    }
                    break;
                case ($relatedEntities !== null):
                    $this->doRefresh($relatedEntities, $visited);
                    break;
                default:
                    // Do nothing
            }
        }
    }

    /**
     * Cascades a detach operation to associated entities.
     *
     * @param object $entity
     * @param array  $visited
     *
     * @return void
     */
    private function cascadeDetach($entity, array &$visited)
    {
        $class               = $this->manager->getClassMetadata(get_class($entity));
        $associationMappings = array_filter(
            $class->getAssociationMappings(),
            function ($assoc) {
                return $assoc['isCascadeDetach'];
            }
        );
        foreach ($associationMappings as $assoc) {
            $relatedEntities = $class->getReflectionProperty($assoc['fieldName'])->getValue($entity);
            switch (true) {
                case ($relatedEntities instanceof ApiCollection):
                    // Unwrap so that foreach() does not initialize
                    $relatedEntities = $relatedEntities->unwrap();
                // break; is commented intentionally!
                case ($relatedEntities instanceof Collection):
                case (is_array($relatedEntities)):
                    foreach ($relatedEntities as $relatedEntity) {
                        $this->doDetach($relatedEntity, $visited);
                    }
                    break;
                case ($relatedEntities !== null):
                    $this->doDetach($relatedEntities, $visited);
                    break;
                default:
                    // Do nothing
            }
        }
    }

    /**
     * Cascades a merge operation to associated entities.
     *
     * @param object $entity
     * @param object $managedCopy
     * @param array  $visited
     *
     * @return void
     */
    private function cascadeMerge($entity, $managedCopy, array &$visited)
    {
        $class               = $this->manager->getClassMetadata(get_class($entity));
        $associationMappings = array_filter(
            $class->getAssociationMappings(),
            function ($assoc) {
                return $assoc['isCascadeMerge'];
            }
        );
        foreach ($associationMappings as $assoc) {
            $relatedEntities = $class->getReflectionProperty($assoc['field'])->getValue($entity);
            if ($relatedEntities instanceof Collection) {
                if ($relatedEntities === $class->getReflectionProperty($assoc['field'])->getValue($managedCopy)) {
                    continue;
                }
                if ($relatedEntities instanceof ApiCollection) {
                    // Unwrap so that foreach() does not initialize
                    $relatedEntities = $relatedEntities->unwrap();
                }
                foreach ($relatedEntities as $relatedEntity) {
                    $this->doMerge($relatedEntity, $visited, $managedCopy, $assoc);
                }
            } else {
                if ($relatedEntities !== null) {
                    $this->doMerge($relatedEntities, $visited, $managedCopy, $assoc);
                }
            }
        }
    }

    /**
     * Cascades the delete operation to associated entities.
     *
     * @param object $entity
     * @param array  $visited
     *
     * @return void
     */
    private function cascadeRemove($entity, array &$visited)
    {
        $class               = $this->manager->getClassMetadata(get_class($entity));
        $associationMappings = array_filter(
            $class->getAssociationMappings(),
            function ($assoc) {
                return $assoc['isCascadeRemove'];
            }
        );
        $entitiesToCascade   = [];
        foreach ($associationMappings as $assoc) {
            if ($entity instanceof Proxy && !$entity->__isInitialized__) {
                $entity->__load();
            }
            $relatedEntities = $class->getReflectionProperty($assoc['fieldName'])->getValue($entity);
            switch (true) {
                case ($relatedEntities instanceof Collection):
                case (is_array($relatedEntities)):
                    // If its a PersistentCollection initialization is intended! No unwrap!
                    foreach ($relatedEntities as $relatedEntity) {
                        $entitiesToCascade[] = $relatedEntity;
                    }
                    break;
                case ($relatedEntities !== null):
                    $entitiesToCascade[] = $relatedEntities;
                    break;
                default:
                    // Do nothing
            }
        }
        foreach ($entitiesToCascade as $relatedEntity) {
            $this->doRemove($relatedEntity, $visited);
        }
    }

    /**
     * Executes any extra updates that have been scheduled.
     */
    private function executeExtraUpdates()
    {
        foreach ($this->extraUpdates as $oid => $update) {
            list ($entity, $changeset) = $update;
            $this->entityChangeSets[$oid] = $changeset;
            $this->getEntityPersister(get_class($entity))->update($entity);
        }
        $this->extraUpdates = [];
    }

    private function executeDeletions(ApiMetadata $class)
    {
        $className = $class->getName();
        $persister = $this->getEntityPersister($className);
        foreach ($this->entityDeletions as $oid => $entity) {
            if ($this->manager->getClassMetadata(get_class($entity))->getName() !== $className) {
                continue;
            }
            $persister->delete($entity);
            unset(
                $this->entityDeletions[$oid],
                $this->entityIdentifiers[$oid],
                $this->originalEntityData[$oid],
                $this->entityStates[$oid]
            );
            // Entity with this $oid after deletion treated as NEW, even if the $oid
            // is obtained by a new entity because the old one went out of scope.
            //$this->entityStates[$oid] = self::STATE_NEW;
            //            if ( ! $class->isIdentifierNatural()) {
            //                $class->getReflectionProperty($class->getIdentifierFieldNames()[0])->setValue($entity, null);
            //            }
        }
    }

    /**
     * @param object $entity
     * @param object $managedCopy
     */
    private function mergeEntityStateIntoManagedCopy($entity, $managedCopy)
    {
        $class = $this->manager->getClassMetadata(get_class($entity));
        foreach ($this->reflectionPropertiesGetter->getProperties($class->getName()) as $prop) {
            $name = $prop->name;
            $prop->setAccessible(true);
            if ($class->hasAssociation($name)) {
                if (!$class->isIdentifier($name)) {
                    $prop->setValue($managedCopy, $prop->getValue($entity));
                }
            } else {
                $assoc2 = $class->getAssociationMapping($name);
                if ($assoc2['type'] & ApiMetadata::TO_ONE) {
                    $other = $prop->getValue($entity);
                    if ($other === null) {
                        $prop->setValue($managedCopy, null);
                    } else {
                        if ($other instanceof Proxy && !$other->__isInitialized()) {
                            // do not merge fields marked lazy that have not been fetched.
                            continue;
                        }
                        if (!$assoc2['isCascadeMerge']) {
                            if ($this->getEntityState($other) === self::STATE_DETACHED) {
                                $targetClass = $this->manager->getClassMetadata($assoc2['targetEntity']);
                                $relatedId   = $targetClass->getIdentifierValues($other);
                                if ($targetClass->getSubclasses()) {
                                    $other = $this->manager->find($targetClass->getName(), $relatedId);
                                } else {
                                    $other = $this->manager->getProxyFactory()->getProxy(
                                        $assoc2['targetEntity'],
                                        $relatedId
                                    );
                                    $this->registerManaged($other, $relatedId, []);
                                }
                            }
                            $prop->setValue($managedCopy, $other);
                        }
                    }
                } else {
                    $mergeCol = $prop->getValue($entity);
                    if ($mergeCol instanceof ApiCollection && !$mergeCol->isInitialized()) {
                        // do not merge fields marked lazy that have not been fetched.
                        // keep the lazy persistent collection of the managed copy.
                        continue;
                    }
                    $managedCol = $prop->getValue($managedCopy);
                    if (!$managedCol) {
                        $managedCol = new ApiCollection(
                            $this->manager,
                            $this->manager->getClassMetadata($assoc2['target']),
                            new ArrayCollection
                        );
                        $managedCol->setOwner($managedCopy, $assoc2);
                        $prop->setValue($managedCopy, $managedCol);
                    }
                    if ($assoc2['isCascadeMerge']) {
                        $managedCol->initialize();
                        // clear and set dirty a managed collection if its not also the same collection to merge from.
                        if (!$managedCol->isEmpty() && $managedCol !== $mergeCol) {
                            $managedCol->unwrap()->clear();
                            $managedCol->setDirty(true);
                            if ($assoc2['isOwningSide']
                                && $assoc2['type'] == ApiMetadata::MANY_TO_MANY
                                && $class->isChangeTrackingNotify()
                            ) {
                                $this->scheduleForDirtyCheck($managedCopy);
                            }
                        }
                    }
                }
            }
            if ($class->isChangeTrackingNotify()) {
                // Just treat all properties as changed, there is no other choice.
                $this->propertyChanged($managedCopy, $name, null, $prop->getValue($managedCopy));
            }
        }
    }

    /**
     * Deletes an entity as part of the current unit of work.
     *
     * This method is internally called during delete() cascades as it tracks
     * the already visited entities to prevent infinite recursions.
     *
     * @param object $entity  The entity to delete.
     * @param array  $visited The map of the already visited entities.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the instance is a detached entity.
     * @throws \UnexpectedValueException
     */
    private function doRemove($entity, array &$visited)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }
        $visited[$oid] = $entity; // mark visited
        // Cascade first, because scheduleForDelete() removes the entity from the identity map, which
        // can cause problems when a lazy proxy has to be initialized for the cascade operation.
        $this->cascadeRemove($entity, $visited);
        $class       = $this->manager->getClassMetadata(get_class($entity));
        $entityState = $this->getEntityState($entity);
        switch ($entityState) {
            case self::STATE_NEW:
            case self::STATE_REMOVED:
                // nothing to do
                break;
            case self::STATE_MANAGED:
                $this->scheduleForDelete($entity);
                break;
            case self::STATE_DETACHED:
                throw new \InvalidArgumentException('Detached entity cannot be removed');
            default:
                throw new \UnexpectedValueException("Unexpected entity state: $entityState." . self::objToStr($entity));
        }
    }

    /**
     * Tests if an entity is loaded - must either be a loaded proxy or not a proxy
     *
     * @param object $entity
     *
     * @return bool
     */
    private function isLoaded($entity)
    {
        return !($entity instanceof Proxy) || $entity->__isInitialized();
    }

    /**
     * Sets/adds associated managed copies into the previous entity's association field
     *
     * @param object $entity
     * @param array  $association
     * @param object $previousManagedCopy
     * @param object $managedCopy
     *
     * @return void
     */
    private function updateAssociationWithMergedEntity($entity, array $association, $previousManagedCopy, $managedCopy)
    {
        $assocField = $association['fieldName'];
        $prevClass  = $this->manager->getClassMetadata(get_class($previousManagedCopy));
        if ($association['type'] & ApiMetadata::TO_ONE) {
            $prevClass->getReflectionProperty($assocField)->setValue($previousManagedCopy, $managedCopy);

            return;
        }
        /** @var array $value */
        $value   = $prevClass->getReflectionProperty($assocField)->getValue($previousManagedCopy);
        $value[] = $managedCopy;
        if ($association['type'] == ApiMetadata::ONE_TO_MANY) {
            $class = $this->manager->getClassMetadata(get_class($entity));
            $class->getReflectionProperty($association['mappedBy'])->setValue($managedCopy, $previousManagedCopy);
        }
    }

    /**
     * Executes a merge operation on an entity.
     *
     * @param object      $entity
     * @param array       $visited
     * @param object|null $prevManagedCopy
     * @param array|null  $assoc
     *
     * @return object The managed copy of the entity.
     *
     * @throws \InvalidArgumentException If the entity instance is NEW.
     * @throws \OutOfBoundsException
     */
    private function doMerge($entity, array &$visited, $prevManagedCopy = null, array $assoc = [])
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            $managedCopy = $visited[$oid];
            if ($prevManagedCopy !== null) {
                $this->updateAssociationWithMergedEntity($entity, $assoc, $prevManagedCopy, $managedCopy);
            }

            return $managedCopy;
        }
        $class = $this->manager->getClassMetadata(get_class($entity));
        // First we assume DETACHED, although it can still be NEW but we can avoid
        // an extra db-roundtrip this way. If it is not MANAGED but has an identity,
        // we need to fetch it from the db anyway in order to merge.
        // MANAGED entities are ignored by the merge operation.
        $managedCopy = $entity;
        if ($this->getEntityState($entity, self::STATE_DETACHED) !== self::STATE_MANAGED) {
            // Try to look the entity up in the identity map.
            $id = $class->getIdentifierValues($entity);
            // If there is no ID, it is actually NEW.
            if (!$id) {
                $managedCopy = $this->newInstance($class);
                $this->persistNew($class, $managedCopy);
            } else {
                $flatId      = ($class->containsForeignIdentifier())
                    ? $this->identifierFlattener->flattenIdentifier($class, $id)
                    : $id;
                $managedCopy = $this->tryGetById($flatId, $class->getRootEntityName());
                if ($managedCopy) {
                    // We have the entity in-memory already, just make sure its not removed.
                    if ($this->getEntityState($managedCopy) == self::STATE_REMOVED) {
                        throw new \InvalidArgumentException('Removed entity cannot be merged');
                    }
                } else {
                    // We need to fetch the managed copy in order to merge.
                    $managedCopy = $this->manager->find($class->getName(), $flatId);
                }
                if ($managedCopy === null) {
                    // If the identifier is ASSIGNED, it is NEW, otherwise an error
                    // since the managed entity was not found.
                    if (!$class->isIdentifierNatural()) {
                        throw new \OutOfBoundsException('Entity not found');
                    }
                    $managedCopy = $this->newInstance($class);
                    $class->setIdentifierValues($managedCopy, $id);
                    $this->persistNew($class, $managedCopy);
                }
            }

            $visited[$oid] = $managedCopy; // mark visited
            if ($this->isLoaded($entity)) {
                if ($managedCopy instanceof Proxy && !$managedCopy->__isInitialized()) {
                    $managedCopy->__load();
                }
                $this->mergeEntityStateIntoManagedCopy($entity, $managedCopy);
            }
            if ($class->isChangeTrackingDeferredExplicit()) {
                $this->scheduleForDirtyCheck($entity);
            }
        }
        if ($prevManagedCopy !== null) {
            $this->updateAssociationWithMergedEntity($entity, $assoc, $prevManagedCopy, $managedCopy);
        }
        // Mark the managed copy visited as well
        $visited[spl_object_hash($managedCopy)] = $managedCopy;
        $this->cascadeMerge($entity, $managedCopy, $visited);

        return $managedCopy;
    }

    /**
     * Executes a detach operation on the given entity.
     *
     * @param object  $entity
     * @param array   $visited
     * @param boolean $noCascade if true, don't cascade detach operation.
     *
     * @return void
     */
    private function doDetach($entity, array &$visited, $noCascade = false)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }
        $visited[$oid] = $entity; // mark visited
        switch ($this->getEntityState($entity, self::STATE_DETACHED)) {
            case self::STATE_MANAGED:
                if ($this->isInIdentityMap($entity)) {
                    $this->removeFromIdentityMap($entity);
                }
                unset(
                    $this->entityInsertions[$oid],
                    $this->entityUpdates[$oid],
                    $this->entityDeletions[$oid],
                    $this->entityIdentifiers[$oid],
                    $this->entityStates[$oid],
                    $this->originalEntityData[$oid]
                );
                break;
            case self::STATE_NEW:
            case self::STATE_DETACHED:
                return;
        }
        if (!$noCascade) {
            $this->cascadeDetach($entity, $visited);
        }
    }

}
