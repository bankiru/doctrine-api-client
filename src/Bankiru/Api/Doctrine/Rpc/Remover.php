<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface Remover extends EntityApiInterface
{
    /**
     * Removes the entity via API
     *
     * @param array $identifier identifiers
     *
     * @return bool Whether operation was successful
     */
    public function remove(array $identifier);
}
