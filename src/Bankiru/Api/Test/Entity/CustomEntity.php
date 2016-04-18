<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 09.02.2016
 * Time: 13:45
 */

namespace Bankiru\Api\Test\Entity;

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
