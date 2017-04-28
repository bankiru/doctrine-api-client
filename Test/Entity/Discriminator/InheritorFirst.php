<?php

namespace Bankiru\Api\Doctrine\Test\Entity\Discriminator;

use Bankiru\Api\Doctrine\Test\Entity\DiscriminatorBaseClass;

class InheritorFirst extends DiscriminatorBaseClass
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
}
