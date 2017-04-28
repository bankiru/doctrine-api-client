<?php

namespace Bankiru\Api\Doctrine\Test\Entity\Discriminator;

class InheritorSecond extends InheritorFirst
{
    /** @var string */
    private $second;

    /**
     * @return string
     */
    public function getSecond()
    {
        return $this->second;
    }
}
