<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 01.02.2016
 * Time: 13:54
 */

namespace Bankiru\Api\Test\Entity;

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
     * @return TestEntity
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
