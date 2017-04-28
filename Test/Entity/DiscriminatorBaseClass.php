<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

class DiscriminatorBaseClass
{
    /** @var int */
    private $id;
    /** @var string */
    private $base;

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
    public function getBase()
    {
        return $this->base;
    }
}
