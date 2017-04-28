<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use ScayTrase\Api\Rpc\Exception\RpcExceptionInterface;

final class ApiCollection extends AbstractLazyCollection implements Selectable
{
    /** @var  ApiEntityManager */
    private $manager;
    /** @var ApiMetadata */
    private $metadata;
    /** @var  object */
    private $owner;
    /** @var  array */
    private $association;
    /** @var  bool */
    private $isDirty = false;
    /** @var  array */
    private $snapshot = [];
    /** @var  string */
    private $backRefFieldName;

    /**
     * ApiCollection constructor.
     *
     * @param ApiEntityManager $manager
     * @param ApiMetadata      $class
     * @param Collection       $collection
     */
    public function __construct(
        ApiEntityManager $manager,
        ApiMetadata $class,
        Collection $collection = null
    ) {
        $this->manager     = $manager;
        $this->metadata    = $class;
        $this->collection  = $collection ?: new ArrayCollection();
        $this->initialized = true;
    }

    /**
     * @return boolean
     */
    public function isDirty()
    {
        return $this->isDirty;
    }

    public function unwrap()
    {
        return $this->collection;
    }

    /**
     * INTERNAL:
     * Tells this collection to take a snapshot of its current state.
     *
     * @return void
     */
    public function takeSnapshot()
    {
        $this->snapshot = $this->collection->toArray();
        $this->isDirty  = false;
    }

    public function getMapping()
    {
        return $this->association;
    }

    /**
     * @return object
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param object $owner
     * @param array  $assoc
     */
    public function setOwner($owner, array $assoc)
    {
        $this->owner            = $owner;
        $this->association      = $assoc;
        $this->backRefFieldName = $assoc['inversedBy'] ?: $assoc['mappedBy'];
    }

    /**
     * @param bool $dirty
     */
    public function setDirty($dirty)
    {
        $this->isDirty = (bool)$dirty;
    }

    /**
     * Initializes the collection by loading its contents from the database
     * if the collection is not yet initialized.
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->initialized || !$this->association) {
            return;
        }
        $this->doInitialize();
        $this->initialized = true;
    }

    /**
     * @return ApiMetadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * INTERNAL:
     * Adds an element to a collection during hydration. This will automatically
     * complete bidirectional associations in the case of a one-to-many association.
     *
     * @param mixed $element The element to add.
     *
     * @return void
     */
    public function hydrateAdd($element)
    {
        $this->collection->add($element);
        // If _backRefFieldName is set and its a one-to-many association,
        // we need to set the back reference.
        if ($this->backRefFieldName && $this->association['type'] === ApiMetadata::ONE_TO_MANY) {
            // Set back reference to owner
            $this->metadata->getReflectionProperty($this->backRefFieldName)->setValue(
                $element,
                $this->owner
            );
            $this->manager->getUnitOfWork()->setOriginalEntityProperty(
                spl_object_hash($element),
                $this->backRefFieldName,
                $this->owner
            );
        }
    }

    /**
     * INTERNAL:
     * Sets a keyed element in the collection during hydration.
     *
     * @param mixed $key     The key to set.
     * @param mixed $element The element to set.
     *
     * @return void
     */
    public function hydrateSet($key, $element)
    {
        $this->collection->set($key, $element);
        // If _backRefFieldName is set, then the association is bidirectional
        // and we need to set the back reference.
        if ($this->backRefFieldName && $this->association['type'] === ApiMetadata::ONE_TO_MANY) {
            // Set back reference to owner
            $this->metadata->getReflectionProperty($this->backRefFieldName)->setValue(
                $element,
                $this->owner
            );
        }
    }

    /**
     * INTERNAL:
     * Returns the last snapshot of the elements in the collection.
     *
     * @return array The last snapshot of the elements.
     */
    public function getSnapshot()
    {
        return $this->snapshot;
    }

    public function setInitialized($state)
    {
        $this->initialized = (bool)$state;
    }

    /**
     * INTERNAL:
     * getDeleteDiff
     *
     * @return array
     */
    public function getDeleteDiff()
    {
        return array_udiff_assoc(
            $this->snapshot,
            $this->collection->toArray(),
            function ($a, $b) {
                return $a === $b ? 0 : 1;
            }
        );
    }

    /**
     * INTERNAL:
     * getInsertDiff
     *
     * @return array
     */
    public function getInsertDiff()
    {
        return array_udiff_assoc(
            $this->collection->toArray(),
            $this->snapshot,
            function ($a, $b) {
                return $a === $b ? 0 : 1;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        $removed = parent::remove($key);
        if (!$removed) {
            return $removed;
        }
        $this->changed();
        if ($this->association !== null &&
            $this->association['type'] & ApiMetadata::TO_MANY &&
            $this->owner &&
            $this->association['orphanRemoval']
        ) {
            $this->manager->getUnitOfWork()->scheduleOrphanRemoval($removed);
        }

        return $removed;
    }

    /**
     * {@inheritdoc}
     */
    public function removeElement($element)
    {
        if (!$this->initialized && $this->association['fetch'] === ApiMetadata::FETCH_EXTRA_LAZY) {
            if ($this->collection->contains($element)) {
                return $this->collection->removeElement($element);
            }
            $persister = $this->manager->getUnitOfWork()->getCollectionPersister($this->association);
            if ($persister->removeElement($this, $element)) {
                return $element;
            }

            return null;
        }
        $removed = parent::removeElement($element);
        if (!$removed) {
            return $removed;
        }
        $this->changed();
        if ($this->association !== null &&
            $this->association['type'] & ApiMetadata::TO_MANY &&
            $this->owner &&
            $this->association['orphanRemoval']
        ) {
            $this->manager->getUnitOfWork()->scheduleOrphanRemoval($element);
        }

        return $removed;
    }

    /**
     * {@inheritdoc}
     */
    public function containsKey($key)
    {
        if (!$this->initialized && $this->association['fetch'] === ApiMetadata::FETCH_EXTRA_LAZY
            && isset($this->association['indexBy'])
        ) {
            $persister = $this->manager->getUnitOfWork()->getCollectionPersister($this->association);

            return $this->collection->containsKey($key) || $persister->containsKey($this, $key);
        }

        return parent::containsKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($element)
    {
        if (!$this->initialized && $this->association['fetch'] === ApiMetadata::FETCH_EXTRA_LAZY) {
            $persister = $this->manager->getUnitOfWork()->getCollectionPersister($this->association);

            return $this->collection->contains($element) || $persister->contains($this, $element);
        }

        return parent::contains($element);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if (!$this->initialized
            && $this->association['fetch'] === ApiMetadata::FETCH_EXTRA_LAZY
            && isset($this->association['indexBy'])
        ) {
            if (!$this->metadata->isIdentifierComposite() &&
                $this->metadata->isIdentifier($this->association['indexBy'])
            ) {
                return $this->manager->find($this->metadata->getName(), $key);
            }

            return $this->manager->getUnitOfWork()->getCollectionPersister($this->association)->get($this, $key);
        }

        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if (!$this->initialized && $this->association['fetch'] === ApiMetadata::FETCH_EXTRA_LAZY) {
            $persister = $this->manager->getUnitOfWork()->getCollectionPersister($this->association);

            return $persister->count($this) + ($this->isDirty ? $this->collection->count() : 0);
        }

        return parent::count();
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        parent::set($key, $value);
        $this->changed();
        if (is_object($value) && $this->manager) {
            $this->manager->getUnitOfWork()->cancelOrphanRemoval($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add($value)
    {
        $this->collection->add($value);
        $this->changed();
        if (is_object($value) && $this->manager) {
            $this->manager->getUnitOfWork()->cancelOrphanRemoval($value);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->containsKey($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }
    /* ArrayAccess implementation */

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (!isset($offset)) {
            return $this->add($value);
        }

        return $this->set($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return $this->collection->isEmpty() && $this->count() === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if ($this->initialized && $this->isEmpty()) {
            return;
        }
        $uow = $this->manager->getUnitOfWork();
        if ($this->association['type'] & ApiMetadata::TO_MANY &&
            $this->association['orphanRemoval'] &&
            $this->owner
        ) {
            // we need to initialize here, as orphan removal acts like implicit cascadeRemove,
            // hence for event listeners we need the objects in memory.
            $this->initialize();
            foreach ($this->collection as $element) {
                $uow->scheduleOrphanRemoval($element);
            }
        }
        $this->collection->clear();
        $this->initialized = true; // direct call, {@link initialize()} is too expensive
        if ($this->association['isOwningSide'] && $this->owner) {
            $this->changed();
            $uow->scheduleCollectionDeletion($this);
            $this->takeSnapshot();
        }
    }

    /**
     * Called by PHP when this collection is serialized. Ensures that only the
     * elements are properly serialized.
     *
     * Internal note: Tried to implement Serializable first but that did not work well
     *                with circular references. This solution seems simpler and works well.
     *
     * @return array
     */
    public function __sleep()
    {
        return ['collection', 'initialized'];
    }

    /**
     * Extracts a slice of $length elements starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param int      $offset
     * @param int|null $length
     *
     * @return array
     */
    public function slice($offset, $length = null)
    {
        if (!$this->initialized && !$this->isDirty && $this->association['fetch'] === ApiMetadata::FETCH_EXTRA_LAZY) {
            $persister = $this->manager->getUnitOfWork()->getCollectionPersister($this->association);

            return $persister->slice($this, $offset, $length);
        }

        return parent::slice($offset, $length);
    }

    /**
     * Cleans up internal state of cloned persistent collection.
     *
     * The following problems have to be prevented:
     * 1. Added entities are added to old PC
     * 2. New collection is not dirty, if reused on other entity nothing
     * changes.
     * 3. Snapshot leads to invalid diffs being generated.
     * 4. Lazy loading grabs entities from old owner object.
     * 5. New collection is connected to old owner and leads to duplicate keys.
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->collection)) {
            $this->collection = clone $this->collection;
        }
        $this->initialize();
        $this->owner    = null;
        $this->snapshot = [];
        $this->changed();
    }

    /**
     * Selects all elements from a selectable that match the expression and
     * return a new collection containing these elements.
     *
     * @param \Doctrine\Common\Collections\Criteria $criteria
     *
     * @return Collection
     *
     * @throws \RuntimeException
     */
    public function matching(Criteria $criteria)
    {
        if ($this->isDirty) {
            $this->initialize();
        }
        if ($this->initialized) {
            return $this->collection->matching($criteria);
        }

        $builder         = Criteria::expr();
        $ownerExpression = $builder->eq($this->backRefFieldName, $this->owner);
        $expression      = $criteria->getWhereExpression();
        $expression      = $expression ? $builder->andX($expression, $ownerExpression) : $ownerExpression;
        $criteria        = clone $criteria;
        $criteria->where($expression);
        $persister = $this->manager->getUnitOfWork()->getEntityPersister($this->association['target']);

        return new LazyCriteriaCollection($persister, $criteria);
    }

    /**
     * Do the initialization logic
     *
     * @return void
     * @throws RpcExceptionInterface
     */
    protected function doInitialize()
    {
        // Has NEW objects added through add(). Remember them.
        $newObjects = [];
        if ($this->isDirty) {
            $newObjects = $this->collection->toArray();
        }
        $this->collection->clear();
        $this->manager->getUnitOfWork()->loadCollection($this);
        $this->takeSnapshot();
        // Reattach NEW objects added through add(), if any.
        if ($newObjects) {
            foreach ($newObjects as $obj) {
                $this->collection->add($obj);
            }
            $this->isDirty = true;
        }
    }

    /**
     * Marks this collection as changed/dirty.
     *
     * @return void
     */
    private function changed()
    {
        $this->isDirty = true;
    }
}
