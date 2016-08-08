<?php

namespace Bankiru\Api\Doctrine;

interface ApiEntityManagerAware
{
    /**
     * @param ApiEntityManager $manager
     */
    public function setApiEntityManager(ApiEntityManager $manager);
}
