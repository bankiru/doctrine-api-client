<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class TestEntity
{
    /** @var  int */
    private $id;
    /** @var  string */
    private $payload;
    /** @var  TestReference[]|ArrayCollection */
    private $references;
    /** @var  TestReference */
    private $parent;

    /**
     * TestEntity constructor.
     */
    public function __construct()
    {
        $this->references = new ArrayCollection();
    }

    /**
     * @return TestReference
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return TestReference[]|ArrayCollection
     */
    public function getReferences()
    {
        return $this->references;
    }
}
