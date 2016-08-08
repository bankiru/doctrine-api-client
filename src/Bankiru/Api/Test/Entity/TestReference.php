<?php

namespace Bankiru\Api\Test\Entity;

class TestReference
{
    /** @var  int */
    private $id;
    /** @var  string */
    private $referencePayload;
    /** @var  TestEntity */
    private $owner;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return TestEntity
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getReferencePayload()
    {
        return $this->referencePayload;
    }
}
