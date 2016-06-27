<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 30.12.2015
 * Time: 8:12
 */

namespace Bankiru\Api\Doctrine\Proxy;

use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\Exception\FetchException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Persister\ApiPersister;
use Bankiru\Api\Doctrine\UnitOfWork;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\Common\Proxy\ProxyDefinition;
use Doctrine\Common\Proxy\ProxyGenerator;
use Doctrine\Common\Util\ClassUtils;

class ProxyFactory extends AbstractProxyFactory
{
    /** @var ClassMetadataFactory */
    private $manager;
    /** @var  UnitOfWork */
    private $uow;

    public function __construct(EntityManager $manager)
    {
        $this->manager  = $manager;
        $this->uow      = $manager->getUnitOfWork();
        $proxyGenerator = new ProxyGenerator(
            $this->manager->getConfiguration()->getProxyDir(),
            $this->manager->getConfiguration()->getProxyNamespace()
        );
        parent::__construct(
            $proxyGenerator,
            $this->manager->getMetadataFactory(),
            $this->manager->getConfiguration()->isAutogenerateProxies()
        );
    }

    /**
     * Determine if this class should be skipped during proxy generation.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $metadata
     *
     * @return bool
     */
    protected function skipClass(ClassMetadata $metadata)
    {
        return $metadata->getReflectionClass()->isAbstract();
    }

    /**
     * @param string $className
     *
     * @return ProxyDefinition
     * @throws FetchException
     */
    protected function createProxyDefinition($className)
    {
        /** @var EntityMetadata $classMetadata */
        $classMetadata = $this->manager->getClassMetadata($className);
        /** @var ApiPersister $persister */
        $persister = $this->uow->getEntityPersister($className);

        return new ProxyDefinition(
            ClassUtils::generateProxyClassName($className, $this->manager->getConfiguration()->getProxyNamespace()),
            $classMetadata->getIdentifierFieldNames(),
            $classMetadata->getReflectionProperties(),
            $this->createInitializer($classMetadata, $persister),
            $this->createCloner($classMetadata, $persister)
        );
    }


    /**
     * Creates a closure capable of initializing a proxy
     *
     * @param ApiMetadata  $classMetadata
     * @param ApiPersister $persister
     *
     * @return \Closure
     * @throws FetchException
     */
    private function createInitializer(ApiMetadata $classMetadata, ApiPersister $persister)
    {
        $wakeupProxy = $classMetadata->getReflectionClass()->hasMethod('__wakeup');

        return function (Proxy $proxy) use ($classMetadata, $wakeupProxy, $persister) {
            $initializer = $proxy->__getInitializer();
            $cloner      = $proxy->__getCloner();

            $proxy->__setInitializer(null);
            $proxy->__setCloner(null);
            if ($proxy->__isInitialized()) {
                return;
            }
            $properties = $proxy->__getLazyProperties();
            foreach ($properties as $propertyName => $property) {
                if (!isset($proxy->$propertyName)) {
                    $proxy->$propertyName = $properties[$propertyName];
                }
            }

            $identifier = $classMetadata->getIdentifierValues($proxy);
            if (null === $persister->loadById($identifier, $proxy)) {
                $proxy->__setInitializer($initializer);
                $proxy->__setCloner($cloner);
                $proxy->__setInitialized(false);
                throw FetchException::notFound();
            }

            $proxy->__setInitialized(true);
            if ($wakeupProxy) {
                $proxy->__wakeup();
            }
        };
    }

    /**
     * Creates a closure capable of finalizing state a cloned proxy
     *
     * @param ApiMetadata  $classMetadata
     * @param ApiPersister $persister
     *
     * @return \Closure
     * @throws FetchException
     */
    private function createCloner(ApiMetadata $classMetadata, ApiPersister $persister)
    {
        return function (Proxy $proxy) use ($classMetadata, $persister) {
            if ($proxy->__isInitialized()) {
                return;
            }
            $proxy->__setInitialized(true);
            $proxy->__setInitializer(null);
            /** @var EntityMetadata $class */
            $class      = $persister->getClassMetadata();
            $identifier = $classMetadata->getIdentifierValues($proxy);

            $original = $persister->loadById($identifier);

            if (null === $original) {
                throw FetchException::notFound();
            }
            foreach ($class->getReflectionClass()->getProperties() as $property) {
                if (!$class->hasField($property->name) && !$class->hasAssociation($property->name)) {
                    continue;
                }
                $property->setAccessible(true);
                $property->setValue($proxy, $property->getValue($original));
            }
        };
    }
}
