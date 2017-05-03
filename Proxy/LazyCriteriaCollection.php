<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;

final class LazyCriteriaCollection extends AbstractLazyCollection
{
    /**
     * @var Selectable
     */
    private $matcher;
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * LazyCriteriaCollection constructor.
     *
     * @param Selectable $matcher
     * @param Criteria   $criteria
     */
    public function __construct(Selectable $matcher, Criteria $criteria)
    {
        $this->matcher  = $matcher;
        $this->criteria = $criteria;
    }

    /**
     * Do the initialization logic
     *
     * @return void
     */
    protected function doInitialize()
    {
        $this->collection = $this->matcher->matching($this->criteria);
    }
}
