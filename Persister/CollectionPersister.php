<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Proxy\LazyCriteriaCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Selectable;

final class CollectionPersister implements Selectable
{
    /** @var ApiEntityManager */
    private $manager;
    /** @var CrudsApiInterface */
    private $api;
    /**
     * @var array
     */
    private $association;

    /**
     * CollectionPersister constructor.
     *
     * @param ApiEntityManager  $manager
     * @param CrudsApiInterface $api
     * @param array             $association
     */
    public function __construct(ApiEntityManager $manager, CrudsApiInterface $api, array $association)
    {
        $this->manager     = $manager;
        $this->api         = $api;
        $this->association = $association;
    }

    public function getManyToManyCollection(array $identifiers)
    {
        $metadata = $this->api->getMetadata();
        if ($metadata->isIdentifierComposite()) {
            throw new \LogicException('Lazy loading entities with composite key is not supported');
        }

        $identifierFields = $metadata->getIdentifierFieldNames();
        $identifierField  = array_shift($identifierFields);

        $criteria = new Criteria();
        $criteria->andWhere(Criteria::expr()->in($identifierField, array_values($identifiers)));

        if (null !== $this->association['orderBy']) {
            $criteria->orderBy($this->association['orderBy']);
        }

        return new LazyCriteriaCollection($this, $criteria);
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
