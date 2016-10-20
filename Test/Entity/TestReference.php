<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

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
     * @param TestEntity $owner
     */
    public function setOwner(TestEntity $owner = null)
    {
        if ($this->owner) {
            $this->owner->getReferences()->removeElement($this);
        }

        $this->owner = $owner;

        if ($this->owner) {
            $this->owner->getReferences()->add($this);
        }
    }

    /**
     * @return string
     */
    public function getReferencePayload()
    {
        return $this->referencePayload;
    }
}
