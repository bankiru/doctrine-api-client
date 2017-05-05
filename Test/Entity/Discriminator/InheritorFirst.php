<?php

namespace Bankiru\Api\Doctrine\Test\Entity\Discriminator;

class InheritorFirst extends AbstractInheritor
{
    /** @var string */
    private $first;

    /**
     * @return string
     */
    public function getFirst()
    {
        return $this->first;
    }

    /**
     * @param string $first
     */
    public function setFirst($first)
    {
        $this->first = $first;
    }
}
