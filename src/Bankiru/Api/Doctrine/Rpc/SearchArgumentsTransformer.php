<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;

/** @internal */
class SearchArgumentsTransformer
{
    /** @var  ApiMetadata */
    private $metadata;
    /** @var  ApiEntityManager */
    private $manager;

    /**
     * SearchArgumentsTransformer constructor.
     *
     * @param ApiMetadata      $metadata
     * @param ApiEntityManager $manager
     */
    public function __construct(ApiMetadata $metadata, ApiEntityManager $manager)
    {
        $this->metadata = $metadata;
        $this->manager  = $manager;
    }

    /**
     * Creates API-ready arguments for search request
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array
     */
    public function transform(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $apiCriteria = [];
        foreach ($criteria as $field => $values) {
            if ($this->metadata->hasAssociation($field)) {
                $mapping = $this->metadata->getAssociationMapping($field);
                /** @var EntityMetadata $target */
                $target = $this->manager->getClassMetadata($mapping['target']);

                $converter = function ($value) use ($target) {
                    if (!is_object($value)) {
                        return $value;
                    }

                    $ids = $target->getIdentifierValues($value);
                    if ($target->isIdentifierComposite) {
                        return $ids;
                    }

                    return array_shift($ids);
                };

                if (is_array($values)) {
                    $values = array_map($converter, $values);
                } else {
                    $values = $converter($values);
                }
            }
            $apiCriteria[$this->metadata->getApiFieldName($field)] = $values;
        }

        $apiOrder = [];
        foreach ((array)$orderBy as $field => $direction) {
            $apiOrder[$this->metadata->getApiFieldName($field)] = $direction;
        }

        return [
            'criteria' => $apiCriteria,
            'order'    => $apiOrder,
            'limit'    => $limit,
            'offset'   => $offset,
        ];
    }
}
