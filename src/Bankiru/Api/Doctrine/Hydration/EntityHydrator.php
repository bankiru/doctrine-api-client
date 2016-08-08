<?php

namespace Bankiru\Api\Doctrine\Hydration;

use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\Exception\HydrationException;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Doctrine\Common\Proxy\Proxy;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class EntityHydrator
{
    /** @var  EntityMetadata */
    private $metadata;
    /** @var  EntityManager */
    private $manager;

    /**
     * EntityHydrator constructor.
     *
     * @param EntityManager $manager
     * @param ApiMetadata   $metadata
     */
    public function __construct(EntityManager $manager, ApiMetadata $metadata)
    {
        $this->manager  = $manager;
        $this->metadata = $metadata;
    }


    /**
     * @param \StdClass   $source
     * @param object|null $entity
     *
     * @return object Hydrated object
     * @throws HydrationException
     */
    public function hydarate($source, $entity = null)
    {
        if (null === $entity) {
            $entity = $this->metadata->getReflectionClass()->newInstance();
        }

        $acessor = new PropertyAccessor();
        foreach ($this->metadata->getFieldNames() as $fieldName) {
            $property = $this->metadata->getReflectionProperty($fieldName);

            $apiField = $this->metadata->getApiFieldName($fieldName);

            try {
                $value = $acessor->getValue($source, $apiField);
            } catch (NoSuchPropertyException $exception) {
                if (!$this->metadata->getFieldMapping($fieldName)['nullable']) {
                    throw new HydrationException(
                        sprintf('Api field %s for property %s does not present in response', $apiField, $fieldName)
                    );

                }

                $property->setValue($entity, null);

                continue;
            }

            $type  =
                $this->manager->getConfiguration()->getTypeRegistry()->get($this->metadata->getTypeOfField($fieldName));
            $value = $type->fromApiValue($value);

            $property->setValue($entity, $value);
        }

        foreach ($this->metadata->getAssociationNames() as $fieldName) {
            $value    = $this->hydrateAssociation($fieldName, $entity, $source);
            $property = $this->metadata->getReflectionProperty($fieldName);
            $property->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * @param string    $field
     * @param \StdClass $source
     * @param object    $entity
     *
     * @return array|Proxy|object
     * @throws HydrationException
     * @throws MappingException
     */
    private function hydrateAssociation($field, $entity, $source)
    {
        $accessor        = new PropertyAccessor();
        $targetClassName = $this->metadata->getAssociationTargetClass($field);
        $mapping         = $this->metadata->getAssociationMapping($field);
        $targetPersister = $this->manager->getUnitOfWork()->getEntityPersister($targetClassName);
        $targetMetadata  = $this->manager->getClassMetadata($mapping['target']);
        $apiField        = $mapping['api_field'];
        $field           = $mapping['field'];


        if ($this->metadata->isSingleValuedAssociation($field)) {
            $identifiers = $this->metadata->getIdentifierValues($entity);
            if ($mapping['isOwningSide']) {
                try {
                    $value = $accessor->getValue($source, $apiField);
                } catch (NoSuchPropertyException $exception) {
                    if ($mapping['nullable']) {
                        return null;
                    }

                    throw new HydrationException(
                        sprintf('Api field %s for property %s does not present in response', $apiField, $field)
                    );
                }

                if ($targetMetadata->isIdentifierComposite()) {
                    throw new HydrationException('Composite references not supported');
                }

                $targetIdsNames = $targetMetadata->getIdentifierFieldNames();
                $targetIdName   = array_shift($targetIdsNames);
                $type           =
                    $this->manager->getConfiguration()
                                  ->getTypeRegistry()
                                  ->get($targetMetadata->getTypeOfField($targetIdName));
                $identifiers    = [$targetIdName => $type->fromApiValue($value)];
            }

            return $targetPersister->getToOneEntity($mapping, $entity, $identifiers);
        }

        if ($this->metadata->isCollectionValuedAssociation($field)) {
            return $targetPersister->getOneToManyCollection($mapping, $entity);
        }

        throw new MappingException('Invalid metadata association type');
    }
}
