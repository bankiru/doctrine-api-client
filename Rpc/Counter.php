<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface Counter extends EntityApiInterface
{
    /**
     * Return API entity count with given parameters
     *
     * @param array $criteria search criteria
     *
     * @return int
     */
    public function count(array $criteria);
}
