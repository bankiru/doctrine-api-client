<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 02.02.2016
 * Time: 16:51
 */

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
