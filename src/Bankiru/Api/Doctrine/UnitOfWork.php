<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Hydration\EntityHydrator;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Persister\ApiPersister;
use Bankiru\Api\Doctrine\Persister\EntityPersister;
use Bankiru\Api\Doctrine\Utility\IdentifierFlattener;
use Doctrine\Common\NotifyPropertyChanged;
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
    /** @var  array */
    private $entityIdentifiers;
    /** @var  object[][] */
    private $identityMap;
    /** @var IdentifierFlattener */
    private $identifierFlattener;
    /** @var  array */
    private $originalEntityData;

    /**
     * UnitOfWork constructor.
     *
     * @param EntityManager $manager
     */
    public function __construct(EntityManager $manager)
    {
        $this->manager             = $manager;
        $this->identifierFlattener = new IdentifierFlattener($this->manager);
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

            $client = $this->manager->getConfiguration()->getRegistry()->get($classMetadata->getClientName());

            $this->persisters[$className] = new ApiPersister(
                $this->manager,
                $this->manager
                    ->getConfiguration()
                    ->getResolver()
                    ->resolve($classMetadata->getApiName())
                    ->createApi(
                        $client,
                        $classMetadata
                    )
            );
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
     * @param object|null $unmanagedProxy
     *
     * @return ObjectManagerAware|object
     * @throws MappingException
     */
    public function getOrCreateEntity($className, \stdClass $data, $unmanagedProxy = null)
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
            $entity = $unmanagedProxy;
            if (null === $entity) {
                $entity = $this->newInstance($class);
            }
            $this->registerManaged($entity, $id, $data);
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
     * Notifies the listener of a property change.
     *
     * @param object $sender       The object on which the property changed.
     * @param string $propertyName The name of the property that changed.
     * @param mixed  $oldValue     The old value of the property that changed.
     * @param mixed  $newValue     The new value of the property that changed.
     *
     * @return void
     */
    public function propertyChanged($sender, $propertyName, $oldValue, $newValue)
    {
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
}
