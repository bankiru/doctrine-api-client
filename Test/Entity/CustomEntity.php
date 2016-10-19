<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

class CustomEntity
{
    /** @var  string */
    private $id;
    /** @var  string */
    private $payload;

    /**
     * @return string
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
}
