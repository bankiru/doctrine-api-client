<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Doctrine\Common\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\ReflectionService;
use ReflectionException;

class EntityMetadataFactory extends AbstractClassMetadataFactory
{
    /** @var  EntityManager */
    private $manager;
    /** @var  MappingDriver */
    private $driver;

    /** @var string[] */
    private $aliases = [];

    public function registerAlias($namespaceAlias, $namespace)
    {
        if (array_key_exists($namespaceAlias, $this->aliases)) {
            throw new \LogicException(sprintf('Alias "%s" is already registered', $namespaceAlias));
        }

        $this->aliases[$namespaceAlias] = rtrim($namespace, '\\');
    }

    /**
     * @param EntityManager $manager
     */
    public function setEntityManager($manager)
    {
        $this->manager = $manager;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadMetadata($name)
    {
        $loaded = parent::loadMetadata($name);
        array_map([$this, 'resolveDiscriminatorValue'], array_map([$this, 'getMetadataFor'], $loaded));

        return $loaded;
    }

    /** {@inheritdoc} */
    protected function initialize()
    {
        $this->driver      = $this->manager->getConfiguration()->getDriver();
        $this->initialized = true;
    }

    /**
     * {@inheritdoc}
     * @throws MappingException
     */
    protected function getFqcnFromAlias($namespaceAlias, $simpleClassName)
    {
        if (!array_key_exists($namespaceAlias, $this->aliases)) {
            throw MappingException::unknownAlias($namespaceAlias);
        }

        return $this->aliases[$namespaceAlias] . $simpleClassName;
    }

    /** {@inheritdoc} */
    protected function wakeupReflection(ClassMetadata $class, ReflectionService $reflService)
    {
        if (!($class instanceof EntityMetadata)) {
            throw new \LogicException('Metadata is not supported');
        }

        /** @var EntityMetadata $class */
        $class->wakeupReflection($reflService);
    }

    /**
     * Initializes Reflection after ClassMetadata was constructed.
     *
     * @param ClassMetadata     $class
     * @param ReflectionService $reflService
     *
     * @return void
     */
    protected function initializeReflection(ClassMetadata $class, ReflectionService $reflService)
    {
        if (!($class instanceof EntityMetadata)) {
            throw new \LogicException('Metadata is not supported');
        }

        /** @var EntityMetadata $class */
        $class->initializeReflection($reflService);
    }

    /**
     * Checks whether the class metadata is an entity.
     *
     * This method should return false for mapped superclasses or embedded classes.
     *
     * @param ClassMetadata $class
     *
     * @return boolean
     */
    protected function isEntity(ClassMetadata $class)
    {
        return true;
    }

    /** {@inheritdoc} */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents)
    {
        /* @var $class EntityMetadata */
        /* @var $parent EntityMetadata */
        if ($parent) {
            $this->addInheritedFields($class, $parent);
            $this->addInheritedRelations($class, $parent);
            $class->setIdentifier($parent->identifier);
            $class->apiFactory     = $parent->apiFactory;
            $class->clientName     = $parent->clientName;
            $class->methodProvider = $parent->methodProvider;

            $class->setIdGeneratorType($parent->generatorType);
            $class->setDiscriminatorField($parent->discriminatorField);
            $class->setDiscriminatorMap($parent->discriminatorMap);

            if ($parent->isMappedSuperclass) {
                $class->setCustomRepositoryClass($parent->repositoryClass);
            }
        }

        // Invoke driver
        try {
            $this->getDriver()->loadMetadataForClass($class->getName(), $class);
        } catch (ReflectionException $e) {
            throw MappingException::nonExistingClass($class->getName());
        }

        if ($class->isRootEntity() && !$class->discriminatorMap) {
            $this->addDefaultDiscriminatorMap($class);
        }
    }

    /**
     * Returns the mapping driver implementation.
     *
     * @return \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver
     */
    protected function getDriver()
    {
        return $this->driver;
    }

    /**
     * Creates a new ClassMetadata instance for the given class name.
     *
     * @param string $className
     *
     * @return ClassMetadata
     */
    protected function newClassMetadataInstance($className)
    {
        return new EntityMetadata($className);
    }

    /**
     * Populates the discriminator value of the given metadata (if not set) by iterating over discriminator
     * map classes and looking for a fitting one.
     *
     * @param EntityMetadata $metadata
     *
     * @return void
     *
     * @throws MappingException
     */
    private function resolveDiscriminatorValue(EntityMetadata $metadata)
    {
        if ($metadata->discriminatorValue
            || !$metadata->discriminatorMap
            || $metadata->isMappedSuperclass
            || !$metadata->reflClass
            || $metadata->reflClass->isAbstract()
        ) {
            return;
        }
        // minor optimization: avoid loading related metadata when not needed
        foreach ($metadata->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($discriminatorClass === $metadata->name) {
                $metadata->discriminatorValue = $discriminatorValue;

                return;
            }
        }
        // iterate over discriminator mappings and resolve actual referenced classes according to existing metadata
        foreach ($metadata->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($metadata->name === $this->getMetadataFor($discriminatorClass)->getName()) {
                $metadata->discriminatorValue = $discriminatorValue;

                return;
            }
        }

        throw MappingException::mappedClassNotPartOfDiscriminatorMap($metadata->name, $metadata->rootEntityName);
    }

    /**
     * Adds a default discriminator map if no one is given
     *
     * If an entity is of any inheritance type and does not contain a
     * discriminator map, then the map is generated automatically. This process
     * is expensive computation wise.
     *
     * The automatically generated discriminator map contains the lowercase short name of
     * each class as key.
     *
     * @param EntityMetadata $class
     *
     * @throws MappingException
     */
    private function addDefaultDiscriminatorMap(EntityMetadata $class)
    {
        $allClasses = $this->driver->getAllClassNames();
        $fqcn       = $class->getName();
        $map        = [$this->getShortName($class->name) => $fqcn];
        $duplicates = [];
        foreach ($allClasses as $subClassCandidate) {
            if (is_subclass_of($subClassCandidate, $fqcn)) {
                $shortName = $this->getShortName($subClassCandidate);
                if (isset($map[$shortName])) {
                    $duplicates[] = $shortName;
                }
                $map[$shortName] = $subClassCandidate;
            }
        }
        if ($duplicates) {
            throw MappingException::duplicateDiscriminatorEntry($class->name, $duplicates, $map);
        }
        $class->setDiscriminatorMap($map);
    }

    /**
     * Gets the lower-case short name of a class.
     *
     * @param string $className
     *
     * @return string
     */
    private function getShortName($className)
    {
        if (strpos($className, "\\") === false) {
            return strtolower($className);
        }
        $parts = explode("\\", $className);

        return strtolower(end($parts));
    }

    /**
     * Adds inherited fields to the subclass mapping.
     *
     * @param EntityMetadata $subClass
     * @param EntityMetadata $parentClass
     *
     * @return void
     */
    private function addInheritedFields(EntityMetadata $subClass, EntityMetadata $parentClass)
    {
        foreach ($parentClass->fields as $mapping) {
            if (!isset($mapping['inherited']) && !$parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }
            if (!isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }
            $subClass->addInheritedFieldMapping($mapping);
        }
        foreach ($parentClass->reflFields as $name => $field) {
            $subClass->reflFields[$name] = $field;
        }
    }

    /**
     * Adds inherited association mappings to the subclass mapping.
     *
     * @param EntityMetadata $subClass
     * @param EntityMetadata $parentClass
     *
     * @return void
     *
     * @throws MappingException
     */
    private function addInheritedRelations(EntityMetadata $subClass, EntityMetadata $parentClass)
    {
        foreach ($parentClass->associations as $mapping) {
            if (!isset($mapping['inherited']) && !$parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }
            if (!isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }
            $subClass->addInheritedAssociationMapping($mapping);
        }
    }
}
