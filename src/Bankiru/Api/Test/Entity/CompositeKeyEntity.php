<?php

namespace Bankiru\Api\Test\Entity;

class CompositeKeyEntity
{
    /** @var  int */
    private $firstKey;
    /** @var  string */
    private $secondKey;
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
    public function getFirstKey()
    {
        return $this->firstKey;
    }

    /**
     * @return string
     */
    public function getSecondKey()
    {
        return $this->secondKey;
    }
}
