<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ScayTrase\Api\Rpc\Exception\RpcExceptionInterface;

class ApiCollection extends AbstractLazyCollection
{
    /** @var  EntityManager */
    private $manager;
    /** @var ApiMetadata */
    private $metadata;
    /** @var  object */
    private $owner;
    /** @var  array */
    private $association;
    /** @var array */
    private $searchArgs;

    /**
     * ApiCollection constructor.
     *
     * @param EntityManager $manager
     * @param ApiMetadata   $class
     * @param array         $searchArgs
     * @param Collection    $collection
     */
    public function __construct(
        EntityManager $manager,
        ApiMetadata $class,
        array $searchArgs,
        Collection $collection = null
    )
    {
        $this->initialized = false;

        $this->manager    = $manager;
        $this->metadata   = $class;
        $this->searchArgs = $searchArgs;
        $this->collection = $collection ?: new ArrayCollection();
    }

    /**
     * @param object $owner
     * @param        $assoc
     */
    public function setOwner($owner, array $assoc)
    {
        $this->owner       = $owner;
        $this->association = $assoc;
    }

    /**
     * Do the initialization logic
     *
     * @return void
     * @throws RpcExceptionInterface
     */
    protected function doInitialize()
    {
        $persister = $this->manager->getUnitOfWork()->getEntityPersister($this->metadata->getName());
        /** @var Collection $collection */
        $collection = call_user_func_array([$persister, 'loadAll'], $this->searchArgs);

        if ($collection instanceof AbstractLazyCollection && $this->owner) {
            $collection = $persister->loadOneToManyCollection($this->association, $this->owner, $collection);
        }

        $this->collection = $collection;
    }
}
