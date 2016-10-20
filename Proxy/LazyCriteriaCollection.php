<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Bankiru\Api\Doctrine\Persister\EntityPersister;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;

class LazyCriteriaCollection extends AbstractLazyCollection
{

    /**
     * LazyCriteriaCollection constructor.
     *
     * @param EntityPersister $persister
     * @param Criteria        $criteria
     */
    public function __construct(EntityPersister $persister, Criteria $criteria)
    {
    }

    /**
     * Do the initialization logic
     *
     * @return void
     */
    protected function doInitialize()
    {
        // TODO: Implement doInitialize() method.
    }
}
