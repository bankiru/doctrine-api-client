<?php

namespace Bankiru\Api\Doctrine\Test\Entity;

class TypeEntity
{
    /** @var int */
    private $id;
    /** @var \DateTime */
    private $datetimeU;
    /** @var \DateTime */
    private $datetimeC;

    /**
     * TypeEntity constructor.
     */
    public function __construct()
    {
        $this->datetimeU = new \DateTime();
        $this->datetimeC = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getDatetimeU()
    {
        return $this->datetimeU;
    }

    /**
     * @param \DateTime $datetimeU
     */
    public function setDatetimeU(\DateTime $datetimeU)
    {
        $this->datetimeU = $datetimeU;
    }

    /**
     * @return \DateTime
     */
    public function getDatetimeC()
    {
        return $this->datetimeC;
    }

    /**
     * @param \DateTime $datetimeC
     */
    public function setDatetimeC(\DateTime $datetimeC)
    {
        $this->datetimeC = $datetimeC;
    }
}
