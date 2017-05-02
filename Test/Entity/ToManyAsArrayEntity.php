<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

use Doctrine\Common\Collections\Collection;

class ToManyAsArrayEntity
{
    /** @var int */
    private $id;

    /** @var string */
    private $payload;

    /** @var ToManyAsArrayEntity[]|Collection */
    private $children;

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
     * @return ToManyAsArrayEntity[]|Collection
     */
    public function getChildren()
    {
        return $this->children;
    }
}
