<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface Searcher extends EntityApiInterface
{
    /**
     * Performs search with given criteria, order, limit and offset
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return \Traversable data for hydration
     */
    public function search(array $criteria = [], array $orderBy = null, $limit = null, $offset = null);
}
