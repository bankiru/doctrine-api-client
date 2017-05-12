<?php

namespace Bankiru\Api\Doctrine\Dehydration;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Doctrine\Common\Collections\Collection;

/** @internal */
final class SearchDehydrator
{
    /** @var  ApiMetadata */
    private $metadata;
    /** @var  ApiEntityManager */
    private $manager;

    /**
     * SearchDehydrator constructor.
     *
     * @param ApiMetadata      $metadata
     * @param ApiEntityManager $manager
     */
    public function __construct(ApiMetadata $metadata, ApiEntityManager $manager)
    {
        $this->metadata = $metadata;
        $this->manager  = $manager;
    }

    /**
     * Converts doctrine object identifiers to API-ready criteria (converts types and field names)
     *
     * @param object $entity
     *
     * @return array API-ready identifier criteria
     */
    public function transformIdentifier($entity)
    {
        return $this->doTransform($this->metadata->getIdentifierValues($entity));
    }

    /**
     * Converts doctrine entity criteria to API-ready criteria (converts types and field names)
     * Appends discriminator for searching
     *
     * @param array $criteria
     *
     * @return array API-ready criteria
     */
    public function transformCriteria(array $criteria)
    {
        $apiCriteria = $this->doTransform($criteria);

        $discriminatorField = $this->metadata->getDiscriminatorField();

        if (null !== $discriminatorField) {
            if (!$this->metadata->getReflectionClass()->isAbstract()) {
                $apiCriteria[$discriminatorField['fieldName']] = [$this->metadata->getDiscriminatorValue()];
            }
            foreach ($this->metadata->getSubclasses() as $subclass) {
                $subClassMetadata = $this->manager->getClassMetadata($subclass);
                if (!$subClassMetadata->getReflectionClass()->isAbstract()) {
                    $apiCriteria[$discriminatorField['fieldName']][] = $subClassMetadata->getDiscriminatorValue();
                }
            }
            sort($apiCriteria[$discriminatorField['fieldName']]);
        }
        
        return $apiCriteria;
    }

    /**
     * Converts doctrine entity criteria to API-ready criteria (converts types and field names)
     *
     * @param array $criteria
     *
     * @return array API-ready criteria
     */
    public function transformFields(array $criteria)
    {
        return $this->doTransform($criteria);
    }

    /**
     * Converts doctrine entity order to API-ready order (converts field names)
     *
     * @param array $orderBy
     *
     * @return array API-ready order
     */
    public function transformOrder(array $orderBy = null)
    {
        $apiOrder = [];
        foreach ((array)$orderBy as $field => $direction) {
            $apiOrder[$this->metadata->getApiFieldName($field)] = $direction;
        }

        return $apiOrder;
    }

    private function doTransform(array $criteria)
    {
        $apiCriteria = [];

        foreach ($criteria as $field => $values) {
            if ($this->metadata->hasAssociation($field)) {
                $mapping = $this->metadata->getAssociationMapping($field);
                /** @var EntityMetadata $target */
                $target = $this->manager->getClassMetadata($mapping['targetEntity']);

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

                if ($values instanceof Collection) {
                    if ($values instanceof ApiCollection && !$values->isInitialized()) {
                        continue;
                    }
                    $values = $values->toArray();
                }

                if (is_array($values)) {
                    $values = array_map($converter, $values);
                } else {
                    $values = $converter($values);
                }
            } else {
                $caster = function ($value) use ($field) {
                    $type = $this->manager
                        ->getConfiguration()
                        ->getTypeRegistry()->get($this->metadata->getTypeOfField($field));

                    return $type->toApiValue($value, $this->metadata->getFieldOptions($field));
                };

                if (is_array($values)) {
                    $values = array_map($caster, $values);
                } else {
                    $values = $caster($values);
                }
            }

            $apiCriteria[$this->metadata->getApiFieldName($field)] = $values;
        }

        return $apiCriteria;
    }
}
