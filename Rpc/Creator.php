<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface Creator extends EntityApiInterface
{
    /**
     * Creates the entity via API request. Should receive the ID back as a part of response
     *
     * @param array $data
     *
     * @return int objects count
     */
    public function create(array $data);
}
