<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Bankiru\Api\Doctrine\Rpc\SearchArgumentsTransformer;
use Doctrine\Common\Collections\AbstractLazyCollection;

/** @internal */
class ApiPersister implements EntityPersister
{
    /** @var SearchArgumentsTransformer */
    private $transformer;
    /** @var  EntityMetadata */
    private $metadata;
    /** @var ApiEntityManager */
    private $manager;
    /** @var CrudsApiInterface */
    private $api;
    /** @var array */
    private $pendingInserts = [];

    /**
     * ApiPersister constructor.
     *
     * @param ApiEntityManager  $manager
     * @param CrudsApiInterface $api
     */
    public function __construct(ApiEntityManager $manager, CrudsApiInterface $api)
    {
        $this->manager     = $manager;
        $this->metadata    = $api->getMetadata();
        $this->api         = $api;
        $this->transformer = new SearchArgumentsTransformer($this->metadata, $this->manager);
    }

    /** {@inheritdoc} */
    public function getClassMetadata()
    {
        return $this->metadata;
    }

    /** {@inheritdoc} */
    public function getCrudsApi()
    {
        return $this->api;
    }

    /** {@inheritdoc} */
    public function update($entity)
    {
        $patch = $this->prepareUpdateData($entity);
        $data  = $this->convertEntityToData($entity);

        $this->api->patch(
            $this->transformer->transformCriteria($this->metadata->getIdentifierValues($entity)),
            $patch,
            $data
        );
    }

    /** {@inheritdoc} */
    public function delete($entity)
    {
        return $this->api->remove($this->transformer->transformCriteria($this->metadata->getIdentifierValues($entity)));
    }

    /** {@inheritdoc} */
    public function count($criteria = [])
    {
        return $this->api->count($this->transformer->transformCriteria($criteria));
    }

    /** {@inheritdoc} */
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $objects = $this->api->search(
            $this->transformer->transformCriteria($criteria),
            $this->transformer->transformOrder($orderBy),
            $limit,
            $offset
        );

        $entities = [];
        foreach ($objects as $object) {
            $entities[] = $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $object);
        }

        return $entities;
    }

    /** {@inheritdoc} */
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

    /** {@inheritdoc} */
    public function loadById(array $identifiers, $entity = null)
    {
        $body = $this->api->find($this->transformer->transformCriteria($identifiers));

        if (null === $body) {
            return null;
        }

        return $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $body);
    }

    /** {@inheritdoc} */
    public function refresh(array $id, $entity)
    {
        $this->loadById($id, $entity);
    }

    /** {@inheritdoc} */
    public function loadOneToManyCollection(array $assoc, $sourceEntity, AbstractLazyCollection $collection)
    {
        $criteria = [
            $assoc['mappedBy'] => $sourceEntity,
        ];

        $orderBy = isset($assoc['orderBy']) ? $assoc['orderBy'] : [];

        $source = $this->api->search(
            $this->transformer->transformCriteria($criteria),
            $this->transformer->transformOrder($orderBy)
        );

        $target = $this->manager->getClassMetadata($assoc['target']);

        foreach ($source as $object) {
            $entity = $this->manager->getUnitOfWork()->getOrCreateEntity($target->getName(), $object);
            if (isset($assoc['orderBy'])) {
                $index = $target->getReflectionProperty($assoc['orderBy'])->getValue($entity);
                $collection->set($index, $entity);
            } else {
                $collection->add($entity);
            }

            $target->getReflectionProperty($assoc['mappedBy'])->setValue($entity, $sourceEntity);
        }

        return $collection;
    }

    /** {@inheritdoc} */
    public function getOneToManyCollection(array $assoc, $sourceEntity, $limit = null, $offset = null)
    {
        $targetClass = $assoc['target'];
        /** @var EntityMetadata $targetMetadata */
        $targetMetadata = $this->manager->getClassMetadata($targetClass);

        if ($this->metadata->isIdentifierComposite) {
            throw new \BadMethodCallException(__METHOD__ . ' on composite reference is not supported');
        }

        $apiCollection = new ApiCollection($this->manager, $targetMetadata);
        $apiCollection->setOwner($sourceEntity, $assoc);
        $apiCollection->setInitialized(false);

        return $apiCollection;
    }

    /** {@inheritdoc} */
    public function getToOneEntity(array $mapping, $sourceEntity, array $identifiers)
    {
        $metadata = $this->manager->getClassMetadata(get_class($sourceEntity));

        if (!$mapping['isOwningSide']) {
            $identifiers = $metadata->getIdentifierValues($sourceEntity);
        }

        return $this->manager->getReference($mapping['target'], $identifiers);
    }

    public function pushNewEntity($entity)
    {
        $this->pendingInserts[] = $entity;
    }

    public function flushNewEntities()
    {
        $result = [];
        foreach ($this->pendingInserts as $entity) {
            $result[] = [
                'generatedId' => $this->getCrudsApi()->create($this->convertEntityToData($entity)),
                'entity'      => $entity,
            ];
        }

        $this->pendingInserts = [];

        if ($this->metadata->isIdentifierNatural()) {
            return [];
        }

        return $result;
    }

    /**
     * Prepares the changeset of an entity for database insertion (UPDATE).
     *
     * The changeset is obtained from the currently running UnitOfWork.
     *
     * During this preparation the array that is passed as the second parameter is filled with
     * <columnName> => <value> pairs, grouped by table name.
     *
     * Example:
     * <code>
     * array(
     *    'foo_table' => array('column1' => 'value1', 'column2' => 'value2', ...),
     *    'bar_table' => array('columnX' => 'valueX', 'columnY' => 'valueY', ...),
     *    ...
     * )
     * </code>
     *
     * @param object $entity The entity for which to prepare the data.
     *
     * @return array The prepared data.
     */
    protected function prepareUpdateData($entity)
    {
        $result = [];
        $uow    = $this->manager->getUnitOfWork();
        foreach ($uow->getEntityChangeSet($entity) as $field => $change) {
            $newVal = $change[1];
            if (!$this->metadata->hasAssociation($field)) {

                $result[$this->metadata->getApiFieldName($field)] = $newVal;
                continue;
            }
            $assoc = $this->metadata->getAssociationMapping($field);
            // Only owning side of x-1 associations can have a FK column.
            if (!$assoc['isOwningSide'] || !($assoc['type'] & ApiMetadata::TO_ONE)) {
                continue;
            }
            if ($newVal !== null) {
                $oid = spl_object_hash($newVal);
                if (isset($this->pendingInserts[$oid]) || $uow->isScheduledForInsert($newVal)) {
                    // The associated entity $newVal is not yet persisted, so we must
                    // set $newVal = null, in order to insert a null value and schedule an
                    // extra update on the UnitOfWork.
                    $uow->scheduleExtraUpdate($entity, [$field => [null, $newVal]]);
                    $newVal = null;
                }
            }
            $newValId = null;
            if ($newVal !== null) {
                $newValId = $uow->getEntityIdentifier($newVal);
            }
            $targetClass                                      =
                $this->manager->getClassMetadata($assoc['target']);
            $result[$this->metadata->getApiFieldName($field)] = $newValId
                ? $newValId[$targetClass->getIdentifierFieldNames()[0]]
                : null;
        }

        return $result;
    }

    private function convertEntityToData($entity)
    {
        $entityData = [];
        foreach ($this->metadata->getReflectionProperties() as $name => $property) {
            if ($this->metadata->isIdentifier($name) && $this->metadata->isIdentifierRemote()) {
                continue;
            }
            $apiField = $this->metadata->getApiFieldName($name);
            $value    = $property->getValue($entity);
            if (null === $value) {
                $entityData[$apiField] = $value;
                continue;
            }

            if ($this->metadata->hasAssociation($name)) {
                $mapping = $this->metadata->getAssociationMapping($name);
                if (($mapping['type'] & ApiMetadata::TO_MANY) && !$mapping['isOwningSide']) {
                    continue;
                }
                $target         = $this->metadata->getAssociationMapping($name)['target'];
                $targetMetadata = $this->manager->getClassMetadata($target);
                $value          = $targetMetadata->getIdentifierValues($value);
                $ids            = [];
                foreach ($value as $idName => $idValue) {
                    $typeName        = $targetMetadata->getTypeOfField($idName);
                    $idApiName       = $targetMetadata->getApiFieldName($idName);
                    $type            = $this->manager->getConfiguration()->getTypeRegistry()->get($typeName);
                    $idValue         = $type->toApiValue($idValue);
                    $ids[$idApiName] = $idValue;
                }
                if (!$targetMetadata->isIdentifierComposite()) {
                    $ids = array_shift($ids);
                }
                $value = $ids;
            } else {
                $typeName = $this->metadata->getTypeOfField($name);
                $type     = $this->manager->getConfiguration()->getTypeRegistry()->get($typeName);
                $value    = $type->toApiValue($value);
            }

            $entityData[$apiField] = $value;
        }

        return $entityData;
    }
}
