<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface Finder extends EntityApiInterface
{
    /**
     * Retrieves single entity source API data
     *
     * @param array $identifier array of identifiers
     *
     * @return \stdClass data for hydration
     */
    public function find(array $identifier);
}
