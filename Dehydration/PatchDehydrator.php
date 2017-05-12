<?php

namespace Bankiru\Api\Doctrine\Dehydration;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Persister\EntityPersister;

/** @internal */
final class PatchDehydrator
{
    /** @var EntityPersister */
    private $persister;
    /** @var ApiMetadata */
    private $metadata;
    /** @var ApiEntityManager */
    private $manager;

    /**
     * PatchDehydrator constructor.
     *
     * @param EntityPersister  $persister
     * @param ApiMetadata      $metadata
     * @param ApiEntityManager $manager
     */
    public function __construct(EntityPersister $persister, ApiMetadata $metadata, ApiEntityManager $manager)
    {
        $this->persister = $persister;
        $this->metadata  = $metadata;
        $this->manager   = $manager;
    }

    public function convertEntityToData($entity)
    {
        $entityData = [];

        $discriminatorField = $this->metadata->getDiscriminatorField();

        if (null !== $discriminatorField) {
            $entityData[$discriminatorField['fieldName']] = $this->metadata->getDiscriminatorValue();
        }

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
                $target         = $this->metadata->getAssociationMapping($name)['targetEntity'];
                $targetMetadata = $this->manager->getClassMetadata($target);
                $value          = $targetMetadata->getIdentifierValues($value);
                $ids            = [];
                foreach ($value as $idName => $idValue) {
                    $typeName        = $targetMetadata->getTypeOfField($idName);
                    $idApiName       = $targetMetadata->getApiFieldName($idName);
                    $type            = $this->manager->getConfiguration()->getTypeRegistry()->get($typeName);
                    $idValue         = $type->toApiValue($idValue, $targetMetadata->getFieldOptions($idName));
                    $ids[$idApiName] = $idValue;
                }
                if (!$targetMetadata->isIdentifierComposite()) {
                    $ids = array_shift($ids);
                }
                $value = $ids;
            } else {
                $typeName = $this->metadata->getTypeOfField($name);
                $type     = $this->manager->getConfiguration()->getTypeRegistry()->get($typeName);
                $value    = $type->toApiValue($value, $this->metadata->getFieldOptions($name));
            }

            $entityData[$apiField] = $value;
        }

        return $entityData;
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
    public function prepareUpdateData($entity)
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
                if ($this->persister->hasPendingUpdates($oid) || $uow->isScheduledForInsert($newVal)) {
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
                $this->manager->getClassMetadata($assoc['targetEntity']);
            $result[$this->metadata->getApiFieldName($field)] = $newValId
                ? $newValId[$targetClass->getIdentifierFieldNames()[0]]
                : null;
        }

        return $result;
    }
}
