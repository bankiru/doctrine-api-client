<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface CrudsApiInterface extends EntityApiInterface
{
    /**
     * Return API entity count with given parameters
     *
     * @param array $criteria search criteria
     *
     * @return int
     */
    public function count(array $criteria);

    /**
     * Creates the entity via API request. Should receive the ID back as a part of response
     *
     * @param array $data
     *
     * @return int objects count
     */
    public function create(array $data);

    /**
     * Retrieves single entity source API data
     *
     * @param array $identifier array of identifiers
     *
     * @return \stdClass data for hydration
     */
    public function find(array $identifier);

    /**
     * Performs update of the entity. If API does not support PATCH-like request - just ignore fields argument
     *
     * @param array    $identifier
     * @param array    $data   Instance of metadata-described entity
     * @param string[] $fields List of modified fields
     *
     * @return void
     */
    public function patch(array $identifier, array $data, array $fields);

    /**
     * Removes the entity via API
     *
     * @param array $identifier identifiers
     *
     * @return bool Whether operation was successful
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
     * @return \Traversable data for hydration
     */
    public function search(array $criteria = [], array $orderBy = null, $limit = null, $offset = null);
}