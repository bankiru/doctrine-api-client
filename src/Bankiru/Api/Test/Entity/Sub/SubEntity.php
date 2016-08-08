<?php

namespace Bankiru\Api\Test\Entity\Sub;

use Bankiru\Api\Test\Entity\TestEntity;

class SubEntity extends TestEntity
{
    /** @var  string */
    private $subPayload;
    /** @var  string|null */
    private $stringPayload;

    /**
     * @return string|null
     */
    public function getStringPayload()
    {
        return $this->stringPayload;
    }

    /**
     * @return string
     */
    public function getSubPayload()
    {
        return $this->subPayload;
    }
}
