<?php

namespace Bankiru\Api\Doctrine\Mapping;

use Bankiru\Api\Doctrine\EntityRepository;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Rpc\Method\MethodProviderInterface;
use Doctrine\Common\Persistence\Mapping\ReflectionService;
use Doctrine\Instantiator\Instantiator;
use Doctrine\Instantiator\InstantiatorInterface;

class EntityMetadata implements ApiMetadata
{
    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var \ReflectionProperty[]
     */
    public $reflFields = [];
    /** @var string */
    public $name;
    /** @var string */
    public $namespace;
    /** @var string */
    public $rootEntityName;
    /** @var string[] */
    public $identifier = [];
    /** @var array */
    public $fields = [];
    /** @var array */
    public $associations = [];
    /** @var string */
    public $repositoryClass = EntityRepository::class;
    /** @var \ReflectionClass */
    public $reflClass;
    /** @var MethodProviderInterface */
    public $methodProvider;
    /** @var string */
    public $clientName;
    /** @var string */
    public $apiName;
    /** @var string[] */
    public $apiFieldNames = [];
    /** @var string[] */
    public $fieldNames = [];
    /** @var bool */
    public $isMappedSuperclass = false;
    /** @var bool */
    public $containsForeignIdentifier;
    /** @var bool */
    public $isIdentifierComposite = false;
    /** @var InstantiatorInterface */
    private $instantiator;
    /** @var  int */
    private $changeTrackingPolicy = self::CHANGETRACKING_DEFERRED_IMPLICIT;

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param string $entityName The name of the entity class the new instance is used for.
     */
    public function __construct($entityName)
    {
        $this->name           = $entityName;
        $this->rootEntityName = $entityName;
    }

    /**
     * @return boolean
     */
    public function containsForeignIdentifier()
    {
        return $this->containsForeignIdentifier;
    }

    /** {@inheritdoc} */
    public function getReflectionProperties()
    {
        return $this->reflFields;
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionProperty($name)
    {
        if (!array_key_exists($name, $this->reflFields)) {
            throw MappingException::noSuchProperty($name, $this->getName());
        }

        return $this->reflFields[$name];
    }

    /** {@inheritdoc} */
    public function getName()
    {
        return $this->name;
    }

    /** {@inheritdoc} */
    public function getMethodContainer()
    {
        return $this->methodProvider;
    }

    /** {@inheritdoc} */
    public function getRepositoryClass()
    {
        return $this->repositoryClass;
    }

    /** {@inheritdoc} */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier)
    {
        $this->identifier            = $identifier;
        $this->isIdentifierComposite = (count($this->identifier) > 1);
    }

    /** {@inheritdoc} */
    public function getReflectionClass()
    {
        if (null === $this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->getName());
        }

        return $this->reflClass;
    }

    /** {@inheritdoc} */
    public function isIdentifier($fieldName)
    {
        return in_array($fieldName, $this->identifier, true);
    }

    /** {@inheritdoc} */
    public function hasField($fieldName)
    {
        return in_array($fieldName, $this->getFieldNames(), true);
    }

    /** {@inheritdoc} */
    public function getFieldNames()
    {
        return array_keys($this->fields);
    }

    /** {@inheritdoc} */
    public function hasAssociation($fieldName)
    {
        return in_array($fieldName, $this->getAssociationNames(), true);
    }

    /** {@inheritdoc} */
    public function getAssociationNames()
    {
        return array_keys($this->associations);
    }

    /** {@inheritdoc} */
    public function isSingleValuedAssociation($fieldName)
    {
        return $this->hasAssociation($fieldName) && $this->associations[$fieldName]['type'] & self::TO_ONE;
    }

    /** {@inheritdoc} */
    public function isCollectionValuedAssociation($fieldName)
    {
        return $this->hasAssociation($fieldName) && $this->associations[$fieldName]['type'] & self::TO_MANY;
    }

    /** {@inheritdoc} */
    public function getIdentifierFieldNames()
    {
        return $this->identifier;
    }

    /** {@inheritdoc} */
    public function getTypeOfField($fieldName)
    {
        return $this->fields[$fieldName]['type'];
    }

    /** {@inheritdoc} */
    public function getAssociationTargetClass($assocName)
    {
        return $this->associations[$assocName]['target'];
    }

    /** {@inheritdoc} */
    public function isAssociationInverseSide($assocName)
    {
        $assoc = $this->associations[$assocName];

        return array_key_exists('mappedBy', $assoc);
    }

    /** {@inheritdoc} */
    public function getAssociationMappedByTargetField($assocName)
    {
        return $this->associations[$assocName]['mappedBy'];
    }

    /** {@inheritdoc} */
    public function getIdentifierValues($object)
    {
        if ($this->isIdentifierComposite) {
            $id = [];
            foreach ($this->identifier as $idField) {
                $value = $this->reflFields[$idField]->getValue($object);
                if ($value !== null) {
                    $id[$idField] = $value;
                }
            }

            return $id;
        }
        $id    = $this->identifier[0];
        $value = $this->reflFields[$id]->getValue($object);
        if (null === $value) {
            return [];
        }

        return [$id => $value];
    }

    /** {@inheritdoc} */
    public function wakeupReflection(ReflectionService $reflService)
    {
        // Restore ReflectionClass and properties
        $this->reflClass    = $reflService->getClass($this->name);
        $this->instantiator = $this->instantiator ?: new Instantiator();

        foreach ($this->fields as $field => $mapping) {
            $class                    = array_key_exists('declared', $mapping) ? $mapping['declared'] : $this->name;
            $this->reflFields[$field] = $reflService->getAccessibleProperty($class, $field);
        }

        foreach ($this->associations as $field => $mapping) {
            $class                    = array_key_exists('declared', $mapping) ? $mapping['declared'] : $this->name;
            $this->reflFields[$field] = $reflService->getAccessibleProperty($class, $field);
        }
    }

    /** {@inheritdoc} */
    public function initializeReflection(ReflectionService $reflService)
    {
        $this->reflClass = $reflService->getClass($this->name);
        $this->namespace = $reflService->getClassNamespace($this->name);
        if ($this->reflClass) {
            $this->name = $this->rootEntityName = $this->reflClass->getName();
        }
    }

    /** {@inheritdoc} */
    public function getApiName()
    {
        if (null === $this->apiName) {
            throw MappingException::noApiSpecified($this->getName());
        }

        return $this->apiName;
    }

    /** {@inheritdoc} */
    public function getClientName()
    {
        if (null === $this->clientName) {
            throw MappingException::noClientSpecified($this->getName());
        }

        return $this->clientName;
    }

    public function mapField(array $mapping)
    {
        $this->validateAndCompleteFieldMapping($mapping);
        $this->assertFieldNotMapped($mapping['field']);
        $this->fields[$mapping['field']] = $mapping;
    }

    /** {@inheritdoc} */
    public function getFieldMapping($fieldName)
    {
        if (!isset($this->fields[$fieldName])) {
            throw MappingException::unknownField($fieldName, $this->getName());
        }

        return $this->fields[$fieldName];
    }

    /** {@inheritdoc} */
    public function getAssociationMapping($fieldName)
    {
        if (!isset($this->associations[$fieldName])) {
            throw MappingException::unknownAssociation($fieldName, $this->getName());
        }

        return $this->associations[$fieldName];
    }

    public function setCustomRepositoryClass($customRepositoryClassName)
    {
        $this->repositoryClass = $customRepositoryClassName;
    }

    /**
     * @internal
     *
     * @param array $mapping
     *
     * @return void
     */
    public function addInheritedFieldMapping(array $mapping)
    {
        $this->fields[$mapping['field']]         = $mapping;
        $this->apiFieldNames[$mapping['field']]  = $mapping['api_field'];
        $this->fieldNames[$mapping['api_field']] = $mapping['field'];
    }

    /** {@inheritdoc} */
    public function getFieldName($apiFieldName)
    {
        return $this->fieldNames[$apiFieldName];
    }

    /** {@inheritdoc} */
    public function getApiFieldName($fieldName)
    {
        return $this->apiFieldNames[$fieldName];
    }

    public function hasApiField($apiFieldName)
    {
        return array_key_exists($apiFieldName, $this->fieldNames);
    }

    public function mapOneToMany(array $mapping)
    {
        $mapping = $this->validateAndCompleteOneToManyMapping($mapping);

        $this->storeMapping($mapping);
    }

    public function mapManyToOne(array $mapping)
    {
        $mapping = $this->validateAndCompleteOneToOneMapping($mapping);

        $this->storeMapping($mapping);
    }

    public function mapOneToOne(array $mapping)
    {
        $mapping = $this->validateAndCompleteOneToOneMapping($mapping);

        $this->storeMapping($mapping);
    }

    /** {@inheritdoc} */
    public function newInstance()
    {
        return $this->instantiator->instantiate($this->name);
    }

    public function isIdentifierComposite()
    {
        return $this->isIdentifierComposite;
    }

    /** {@inheritdoc} */
    public function getRootEntityName()
    {
        return $this->rootEntityName;
    }

    /**
     * Populates the entity identifier of an entity.
     *
     * @param object $entity
     * @param array  $id
     *
     * @return void
     */
    public function assignIdentifier($entity, array $id)
    {
        foreach ($id as $idField => $idValue) {
            $this->reflFields[$idField]->setValue($entity, $idValue);
        }
    }

    public function addInheritedAssociationMapping(array $mapping)
    {
        $this->associations[$mapping['field']]   = $mapping;
        $this->apiFieldNames[$mapping['field']]  = $mapping['api_field'];
        $this->fieldNames[$mapping['api_field']] = $mapping['field'];
    }

    /** {@inheritdoc} */
    public function getSubclasses()
    {
        //fixme
        return [];
    }

    /** {@inheritdoc} */
    public function getAssociationMappings()
    {
        return $this->associations;
    }

    /**
     * Validates & completes the basic mapping information that is common to all
     * association mappings (one-to-one, many-ot-one, one-to-many, many-to-many).
     *
     * @param array $mapping The mapping.
     *
     * @return array The updated mapping.
     *
     * @throws MappingException If something is wrong with the mapping.
     */
    protected function validateAndCompleteAssociationMapping(array $mapping)
    {
        if (!array_key_exists('api_field', $mapping)) {
            $mapping['api_field'] = $mapping['field'];
        }

        if (!isset($mapping['mappedBy'])) {
            $mapping['mappedBy'] = null;
        }

        if (!isset($mapping['inversedBy'])) {
            $mapping['inversedBy'] = null;
        }

        $mapping['isOwningSide'] = true; // assume owning side until we hit mappedBy

        // unset optional indexBy attribute if its empty
        if (!isset($mapping['indexBy']) || !$mapping['indexBy']) {
            unset($mapping['indexBy']);
        }

        // If targetEntity is unqualified, assume it is in the same namespace as
        // the sourceEntity.
        $mapping['source'] = $this->name;
        if (isset($mapping['target'])) {
            $mapping['target'] = ltrim($mapping['target'], '\\');
        }

        if (($mapping['type'] & self::MANY_TO_ONE) > 0 &&
            isset($mapping['orphanRemoval']) &&
            $mapping['orphanRemoval'] == true
        ) {
            throw new MappingException(
                sprintf('Illegal orphanRemoval %s for %s', $mapping['field'], $this->name)
            );
        }

        // Complete id mapping
        if (isset($mapping['id']) && $mapping['id'] === true) {
            if (isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'] == true) {
                throw new MappingException(
                    sprintf('Illegal orphanRemoval on identifier association %s for %s', $mapping['field'], $this->name)
                );
            }

            if (!in_array($mapping['field'], $this->identifier, true)) {
                $this->identifier[]              = $mapping['field'];
                $this->containsForeignIdentifier = true;
            }

            // Check for composite key
            if (!$this->isIdentifierComposite && count($this->identifier) > 1) {
                $this->isIdentifierComposite = true;
            }
        }

        // Mandatory and optional attributes for either side
        if (null !== $mapping['mappedBy']) {
            $mapping['isOwningSide'] = false;
        }

        if (isset($mapping['id']) && $mapping['id'] === true && $mapping['type'] & self::TO_MANY) {
            throw new MappingException(
                sprintf('Illegal toMany identifier association %s for %s', $mapping['field'], $this->name)
            );
        }

        // Fetch mode. Default fetch mode to LAZY, if not set.
        if ( ! isset($mapping['fetch'])) {
            $mapping['fetch'] = self::FETCH_LAZY;
        }

        // Cascades
        $cascades    = isset($mapping['cascade']) ? array_map('strtolower', $mapping['cascade']) : [];
        $allCascades = ['remove', 'persist', 'refresh', 'merge', 'detach'];
        if (in_array('all', $cascades, true)) {
            $cascades = $allCascades;
        } elseif (count($cascades) !== count(array_intersect($cascades, $allCascades))) {
            throw new MappingException('Invalid cascades: ' . implode(', ', $cascades));
        }
        $mapping['cascade']          = $cascades;
        $mapping['isCascadeRemove']  = in_array('remove', $cascades, true);
        $mapping['isCascadePersist'] = in_array('persist', $cascades, true);
        $mapping['isCascadeRefresh'] = in_array('refresh', $cascades, true);
        $mapping['isCascadeMerge']   = in_array('merge', $cascades, true);
        $mapping['isCascadeDetach']  = in_array('detach', $cascades, true);

        return $mapping;
    }

    private function storeMapping(array $mapping)
    {
        $this->assertFieldNotMapped($mapping['field']);

        $this->apiFieldNames[$mapping['field']]  = $mapping['api_field'];
        $this->fieldNames[$mapping['api_field']] = $mapping['field'];
        $this->associations[$mapping['field']]   = $mapping;
    }

    private function validateAndCompleteFieldMapping(array &$mapping)
    {
        if (!array_key_exists('api_field', $mapping)) {
            $mapping['api_field'] = $mapping['field']; //todo: invent naming strategy
        }

        $this->apiFieldNames[$mapping['field']]  = $mapping['api_field'];
        $this->fieldNames[$mapping['api_field']] = $mapping['field'];

        // Complete id mapping
        if (isset($mapping['id']) && $mapping['id'] === true) {
            if (!in_array($mapping['field'], $this->identifier, true)) {
                $this->identifier[] = $mapping['field'];
            }
            // Check for composite key
            if (!$this->isIdentifierComposite && count($this->identifier) > 1) {
                $this->isIdentifierComposite = true;
            }
        }
    }

    /**
     * @param string $fieldName
     *
     * @throws MappingException
     */
    private function assertFieldNotMapped($fieldName)
    {
        if (array_key_exists($fieldName, $this->fields) ||
            array_key_exists($fieldName, $this->associations) ||
            array_key_exists($fieldName, $this->identifier)
        ) {
            throw new MappingException('Field already mapped');
        }
    }

    /**
     * @param array $mapping
     *
     * @return array
     * @throws MappingException
     * @throws \InvalidArgumentException
     */
    private function validateAndCompleteOneToManyMapping(array $mapping)
    {
        $mapping = $this->validateAndCompleteAssociationMapping($mapping);

        // OneToMany-side MUST be inverse (must have mappedBy)
        if (!isset($mapping['mappedBy'])) {
            throw new MappingException(
                sprintf('Many to many requires mapped by: %s', $mapping['field'])
            );
        }
        $mapping['orphanRemoval']   = isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'];
        $mapping['isCascadeRemove'] = $mapping['orphanRemoval'] || $mapping['isCascadeRemove'];
        $this->assertMappingOrderBy($mapping);

        return $mapping;
    }

    /**
     * @param array $mapping
     *
     * @throws \InvalidArgumentException
     */
    private function assertMappingOrderBy(array $mapping)
    {
        if (array_key_exists('orderBy', $mapping) && !is_array($mapping['orderBy'])) {
            throw new \InvalidArgumentException(
                "'orderBy' is expected to be an array, not " . gettype($mapping['orderBy'])
            );
        }
    }

    /**
     * @param array $mapping
     *
     * @return array
     */
    private function validateAndCompleteOneToOneMapping(array $mapping)
    {
        $mapping = $this->validateAndCompleteAssociationMapping($mapping);

        $mapping['orphanRemoval']   = isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'];
        $mapping['isCascadeRemove'] = $mapping['orphanRemoval'] || $mapping['isCascadeRemove'];
        if ($mapping['orphanRemoval']) {
            unset($mapping['unique']);
        }

        return $mapping;
    }

    public function isReadOnly()
    {
        return false;
    }

    /**
     * Sets the change tracking policy used by this class.
     *
     * @param integer $policy
     *
     * @return void
     */
    public function setChangeTrackingPolicy($policy)
    {
        $this->changeTrackingPolicy = $policy;
    }
    /**
     * Whether the change tracking policy of this class is "deferred explicit".
     *
     * @return boolean
     */
    public function isChangeTrackingDeferredExplicit()
    {
        return $this->changeTrackingPolicy == self::CHANGETRACKING_DEFERRED_EXPLICIT;
    }
    /**
     * Whether the change tracking policy of this class is "deferred implicit".
     *
     * @return boolean
     */
    public function isChangeTrackingDeferredImplicit()
    {
        return $this->changeTrackingPolicy == self::CHANGETRACKING_DEFERRED_IMPLICIT;
    }
    /**
     * Whether the change tracking policy of this class is "notify".
     *
     * @return boolean
     */
    public function isChangeTrackingNotify()
    {
        return $this->changeTrackingPolicy == self::CHANGETRACKING_NOTIFY;
    }

}
