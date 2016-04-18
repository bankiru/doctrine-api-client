<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 03.02.2016
 * Time: 8:26
 */

namespace Bankiru\Api\Test\Entity;

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
