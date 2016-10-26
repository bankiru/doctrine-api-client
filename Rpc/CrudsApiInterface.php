<?php

namespace Bankiru\Api\Doctrine\Rpc;

use ScayTrase\Api\Rpc\Exception\RemoteCallFailedException;

interface CrudsApiInterface extends EntityApiInterface
{
    /**
     * Return API entity count by given criteria
     *
     * @param array $criteria search criteria
     *
     * @return int
     * @throws RemoteCallFailedException
     */
    public function count(array $criteria);

    /**
     * Creates the entity via API request.
     * Should receive the ID back as a part of response if entity uses REMOTE generator strategy for generation
     *
     * @param array $data
     *
     * @return int|null objects count
     * @throws RemoteCallFailedException
     */
    public function create(array $data);

    /**
     * Retrieves single entity source API data
     *
     * @param array $identifier array of identifiers
     *
     * @return \stdClass data for hydration
     * @throws RemoteCallFailedException
     */
    public function find(array $identifier);

    /**
     * Performs patch-update of the entity
     *
     * @param array $identifier
     * @param array $patch
     * @param array $entity
     *
     * @throws RemoteCallFailedException
     */
    public function patch(array $identifier, array $patch, array $entity);

    /**
     * Removes the entity via API
     *
     * @param array $identifier identifiers
     *
     * @throws RemoteCallFailedException
     */
    public function remove(array $identifier);

    /**
     * Performs search with given criteria, order, limit and offset
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return \Traversable|\stdClass[] Traversable of \stdClass instances to hydrate objects from
     * @throws RemoteCallFailedException
     */
    public function search(array $criteria = [], array $orderBy = null, $limit = null, $offset = null);
}
