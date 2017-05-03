<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Proxy\LazyCriteriaCollection;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;

final class CollectionPersister
{
    /**
     * @var array
     */
    private $association;
    /**
     * @var ApiMetadata
     */
    private $metadata;
    /**
     * @var Selectable
     */
    private $matcher;

    /**
     * CollectionPersister constructor.
     *
     * @param ApiMetadata $metadata
     * @param Selectable  $matcher
     * @param array       $association
     *
     * @internal param CrudsApiInterface $api
     */
    public function __construct(ApiMetadata $metadata, Selectable $matcher, array $association)
    {
        $this->association = $association;
        $this->metadata    = $metadata;
        $this->matcher     = $matcher;
    }

    public function getManyToManyCollection(array $identifiers)
    {
        if ($this->metadata->isIdentifierComposite()) {
            throw new \LogicException('Lazy loading entities with composite key is not supported');
        }

        $identifierFields = $this->metadata->getIdentifierFieldNames();
        $identifierField  = array_shift($identifierFields);

        $criteria = new Criteria();
        $criteria->andWhere(Criteria::expr()->in($identifierField, array_values($identifiers)));

        if (null !== $this->association['orderBy']) {
            $criteria->orderBy($this->association['orderBy']);
        }

        return new LazyCriteriaCollection($this->matcher, $criteria);
    }
}
