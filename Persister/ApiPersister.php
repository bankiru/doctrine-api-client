<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Bankiru\Api\Doctrine\Rpc\SearchArgumentsTransformer;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;

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
        $data   = [];
        $fields = [];

        //todo: fill data with raw data for API and fields with modified fields pending to update

        $this->api->patch($data, $fields);
    }

    /** {@inheritdoc} */
    public function delete($entity)
    {
        return $this->api->remove($this->metadata->getIdentifierValues($entity));
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
        if (!$objects instanceof \Traversable) {
            $objects = new \ArrayIterator($objects);
        }

        $entities = [];
        foreach ($objects as $object) {
            $entities[] = $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $object);
        }

        return new ArrayCollection($entities);
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

        $entity = $this->manager->getUnitOfWork()->getOrCreateEntity($this->metadata->getName(), $body, $entity);

        return $entity;
    }

    /** {@inheritdoc} */
    public function refresh(array $id, $entity)
    {
        $this->loadById($id, $entity);
    }

    /** {@inheritdoc} */
    public function loadOneToManyCollection(array $assoc, $sourceEntity, AbstractLazyCollection $collection)
    {
        if ($collection instanceof ApiCollection) {
            foreach ($collection->getIterator() as $entity) {
                $this->metadata->getReflectionProperty($assoc['mappedBy'])->setValue($entity, $sourceEntity);
            }
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

    /** {@inheritdoc} */
    public function getToOneEntity(array $mapping, $sourceEntity, array $identifiers)
    {
        $metadata = $this->manager->getClassMetadata(get_class($sourceEntity));

        if (!$mapping['isOwningSide']) {
            $identifiers = $metadata->getIdentifierValues($sourceEntity);
        }

        return $this->manager->getReference($mapping['target'], $identifiers);
    }
}
