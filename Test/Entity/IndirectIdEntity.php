<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

class IndirectIdEntity
{
    /** @var  int */
    private $id;
    /** @var  string */
    private $payload;

    /**
     * @return string
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
