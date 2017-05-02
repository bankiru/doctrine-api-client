<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Dehydration\PatchDehydrator;
use Bankiru\Api\Doctrine\Dehydration\SearchDehydrator;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Doctrine\Common\Collections\AbstractLazyCollection;

/** @internal */
final class ApiPersister implements EntityPersister
{
    /** @var  EntityMetadata */
    private $metadata;
    /** @var ApiEntityManager */
    private $manager;
    /** @var CrudsApiInterface */
    private $api;
    /** @var array */
    private $pendingInserts = [];
    /** @var SearchDehydrator */
    private $searchDehydrator;
    /** @var PatchDehydrator */
    private $patchDehydrator;

    /**
     * ApiPersister constructor.
     *
     * @param ApiEntityManager  $manager
     * @param CrudsApiInterface $api
     */
    public function __construct(ApiEntityManager $manager, CrudsApiInterface $api)
    {
        $this->manager          = $manager;
        $this->metadata         = $api->getMetadata();
        $this->api              = $api;
        $this->searchDehydrator = new SearchDehydrator($this->metadata, $this->manager);
        $this->patchDehydrator  = new PatchDehydrator($this, $this->metadata, $this->manager);
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
        $patch = $this->patchDehydrator->prepareUpdateData($entity);
        $data  = $this->patchDehydrator->convertEntityToData($entity);

        $this->api->patch($this->searchDehydrator->transformIdentifier($entity), $patch, $data);
    }

    /** {@inheritdoc} */
    public function delete($entity)
    {
        return $this->api->remove($this->searchDehydrator->transformIdentifier($entity));
    }

    /** {@inheritdoc} */
    public function count($criteria = [])
    {
        return $this->api->count($this->searchDehydrator->transformCriteria($criteria));
    }

    /** {@inheritdoc} */
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $objects = $this->api->search(
            $this->searchDehydrator->transformCriteria($criteria),
            $this->searchDehydrator->transformOrder($orderBy),
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
        $body = $this->api->find($this->searchDehydrator->transformFields($identifiers));

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
            $this->searchDehydrator->transformCriteria($criteria),
            $this->searchDehydrator->transformOrder($orderBy)
        );

        $target = $this->manager->getClassMetadata($assoc['target']);

        foreach ($source as $object) {
            $entity = $this->manager->getUnitOfWork()->getOrCreateEntity($target->getName(), $object);
            if (isset($assoc['indexBy'])) {
                $index = $target->getReflectionProperty($assoc['indexBy'])->getValue($entity);
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
        $this->pendingInserts[spl_object_hash($entity)] = $entity;
    }

    public function flushNewEntities()
    {
        $result = [];
        foreach ($this->pendingInserts as $entity) {
            $result[] = [
                'generatedId' => $this->getCrudsApi()->create($this->patchDehydrator->convertEntityToData($entity)),
                'entity'      => $entity,
            ];
        }

        $this->pendingInserts = [];

        if ($this->metadata->isIdentifierNatural()) {
            return [];
        }

        return $result;
    }

    /** {@inheritdoc} */
    public function hasPendingUpdates($oid)
    {
        return isset($this->pendingInserts[$oid]);
    }
}
