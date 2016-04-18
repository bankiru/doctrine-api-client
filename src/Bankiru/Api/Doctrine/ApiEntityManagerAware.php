<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 11.04.2016
 * Time: 14:03
 */

namespace Bankiru\Api\Doctrine;

interface ApiEntityManagerAware
{
    /**
     * @param ApiEntityManager $manager
     */
    public function setApiEntityManager(ApiEntityManager $manager);
}
