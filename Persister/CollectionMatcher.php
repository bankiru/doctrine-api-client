<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;

final class CollectionMatcher implements Selectable
{
    /** @var CrudsApiInterface */
    private $api;
    /** @var ApiEntityManager */
    private $manager;

    /**
     * CollectionMatcher constructor.
     *
     * @param CrudsApiInterface $api
     * @param ApiEntityManager  $manager
     */
    public function __construct(ApiEntityManager $manager, CrudsApiInterface $api)
    {
        $this->api     = $api;
        $this->manager = $manager;
    }

    public function matching(Criteria $criteria)
    {
        if ($this->api instanceof Selectable) {
            return $this->api->matching($criteria);
        }

        return $this->search($criteria);
    }

    private function search(Criteria $criteria)
    {
        $expr = $criteria->getWhereExpression();

        $filter = [];
        if ($expr) {
            $visitor = new SimpleCriteriaVisitor();
            $filter  = $visitor->dispatch($expr);
        }

        $orderings = $criteria->getOrderings();
        $offset    = $criteria->getFirstResult();
        $length    = $criteria->getMaxResults();

        $data = $this->api->search($filter, $orderings, $offset, $length);

        $entities = [];
        foreach ($data as $object) {
            $entities[] = $this->manager->getUnitOfWork()->getOrCreateEntity(
                $this->api->getMetadata()->getName(),
                $object
            );
        }

        return new ArrayCollection($entities);
    }
}
