<?php

namespace Bankiru\Api\Doctrine\Persister;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Proxy\LazyCriteriaCollection;
use Bankiru\Api\Doctrine\Utility\IdentifierFixer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @var ApiEntityManager
     */
    private $manager;

    /**
     * CollectionPersister constructor.
     *
     * @param ApiEntityManager $manager
     * @param ApiMetadata      $metadata
     * @param Selectable       $matcher
     * @param array            $association
     *
     * @internal param CrudsApiInterface $api
     */
    public function __construct(
        ApiEntityManager $manager,
        ApiMetadata $metadata,
        Selectable $matcher,
        array $association
    ) {
        $this->association = $association;
        $this->metadata    = $metadata;
        $this->matcher     = $matcher;
        $this->manager     = $manager;
    }

    /**
     * Returns many to many collection for given identifiers.
     *
     * If every identifier is present in identifier map then initialized ArrayCollection is returned immediately
     *
     * @param array $identifiers
     *
     * @return Collection
     */
    public function getManyToManyCollection(array $identifiers)
    {
        if ($this->metadata->isIdentifierComposite()) {
            throw new \LogicException('Lazy loading entities with composite key is not supported');
        }

        $collection = $this->prefetch($identifiers);

        if (count($identifiers) === 0) {
            return $collection;
        }

        return $this->createLazyCriteria($identifiers, $collection);
    }

    /**
     * @param array $identifiers
     * @param       $collection
     *
     * @return LazyCriteriaCollection
     */
    private function createLazyCriteria(array $identifiers, $collection)
    {
        $identifierFields = $this->metadata->getIdentifierFieldNames();
        $identifierField  = array_shift($identifierFields);

        $criteria = new Criteria();
        $criteria->andWhere(Criteria::expr()->in($identifierField, array_values($identifiers)));

        if (null !== $this->association['orderBy']) {
            $criteria->orderBy($this->association['orderBy']);
        }

        return new LazyCriteriaCollection($this->matcher, $criteria, $collection);
    }

    /**
     * Prefetch some elements from identity map
     *
     * @param array $identifiers
     *
     * @return ArrayCollection
     */
    private function prefetch(array &$identifiers)
    {
        $prefetched = [];
        foreach ($identifiers as $key => $id) {
            $id = IdentifierFixer::fixScalarId($id, $this->metadata);
            if (false !== ($entity = $this->manager->getUnitOfWork()->tryGetById($id, $this->metadata->getName()))) {
                $prefetched[] = $entity;
                unset($identifiers[$key]);
            }
        }

        return new ArrayCollection($prefetched);
    }
}
