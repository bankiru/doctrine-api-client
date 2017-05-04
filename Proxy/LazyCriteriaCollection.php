<?php

namespace Bankiru\Api\Doctrine\Proxy;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @var Collection
     */
    private $prefetched;

    /**
     * LazyCriteriaCollection constructor.
     *
     * @param Selectable $matcher
     * @param Criteria   $criteria
     * @param Collection $prefetched
     */
    public function __construct(Selectable $matcher, Criteria $criteria, Collection $prefetched = null)
    {
        $this->matcher    = $matcher;
        $this->criteria   = $criteria;
        $this->prefetched = $prefetched ?: new ArrayCollection();
    }

    /**
     * Do the initialization logic
     *
     * @return void
     */
    protected function doInitialize()
    {
        $this->collection = new ArrayCollection();

        foreach ($this->prefetched as $element) {
            $this->collection->add($element);
        }

        foreach ($this->matcher->matching($this->criteria) as $element) {
            if (!$this->collection->contains($element)) {
                $this->collection->add($element);
            }
        }

        $this->collection = $this->collection->matching($this->criteria);
    }
}
