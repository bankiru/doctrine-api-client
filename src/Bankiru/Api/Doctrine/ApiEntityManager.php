<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 25.02.2016
 * Time: 17:50
 */

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Proxy\ProxyFactory;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Interface ApiEntityManager
 *
 * @package Bankiru\Api\Doctrine
 * @method ApiMetadata getClassMetadata($className)
 */
interface ApiEntityManager extends ObjectManager
{
    /**
     * @return Configuration
     */
    public function getConfiguration();

    /**
     * @return ProxyFactory
     */
    public function getProxyFactory();

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork();

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @param string $entityName The name of the entity type.
     * @param mixed  $id         The entity identifier.
     *
     * @return object The entity reference.
     */
    public function getReference($entityName, $id);

    /**
     * @return ApiEntityCache
     */
    public function getEntityCache();
}
