<?php

namespace Bankiru\Api\Test\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PrefixedEntity
{
    /** @var string */
    private $id;

    /** @var string */
    private $payload;

    /** @var PrefixedEntity */
    private $parent;

    /** @var PrefixedEntity[]|Collection */
    private $children;

    /**
     * PrefixedEntity constructor.
     */
    public function __construct()
    {
        $this->children = new ArrayCollection();
    }


    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return PrefixedEntity
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return PrefixedEntity[]
     */
    public function getChildren()
    {
        return $this->children;
    }
}
