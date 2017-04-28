<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Bankiru\Api\Doctrine\Persister\EntityPersister;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;

final class LazyCriteriaCollection extends AbstractLazyCollection
{
    /**
     * @var EntityPersister
     */
    private $persister;
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * LazyCriteriaCollection constructor.
     *
     * @param EntityPersister $persister
     * @param Criteria        $criteria
     */
    public function __construct(EntityPersister $persister, Criteria $criteria)
    {
        $this->persister = $persister;
        $this->criteria = $criteria;
    }

    /**
     * Do the initialization logic
     *
     * @return void
     */
    protected function doInitialize()
    {
        $this->collection = [];
    }
}
