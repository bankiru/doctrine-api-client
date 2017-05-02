<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Bankiru\Api\Doctrine\Persister\CollectionPersister;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;

final class LazyCriteriaCollection extends AbstractLazyCollection
{
    /**
     * @var CollectionPersister
     */
    private $persister;
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * LazyCriteriaCollection constructor.
     *
     * @param CollectionPersister $persister
     * @param Criteria            $criteria
     */
    public function __construct(CollectionPersister $persister, Criteria $criteria)
    {
        $this->persister = $persister;
        $this->criteria  = $criteria;
    }

    /**
     * Do the initialization logic
     *
     * @return void
     */
    protected function doInitialize()
    {
        $this->collection = $this->persister->matching($this->criteria);
    }
}
