<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Exception\ApiCallException;
use ScayTrase\Api\Rpc\RpcRequestInterface;

/** @internal */
abstract class SingleRequestApi implements CrudsApiInterface
{
    /** {@inheritdoc} */
    public function count(array $criteria = [])
    {
        $request  = $this->createCountRequest($criteria);
        $response = $this->getClient()->invoke($request)->getResponse($request);

        if (!$response->isSuccessful()) {
            throw ApiCallException::callFailed($response);
        }

        return (int)$response->getBody();
    }

    /** {@inheritdoc} */
    public function find(array $identifier)
    {
        $request  = $this->createFindRequest($identifier);
        $response = $this->getClient()->invoke([$request])->getResponse($request);

        if (!$response->isSuccessful()) {
            throw ApiCallException::callFailed($response);
        }

        return $response->getBody();
    }

    /** {@inheritdoc} */
    public function search(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $request  = $this->createSearchRequest($criteria, $orderBy, $limit, $offset);
        $response = $this->getClient()->invoke($request)->getResponse($request);

        if (!$response->isSuccessful()) {
            throw ApiCallException::callFailed($response);
        }

        return new \ArrayIterator($response->getBody());
    }

    /**
     * @param array $criteria
     *
     * @return RpcRequestInterface
     */
    abstract protected function createCountRequest(array $criteria = []);

    /**
     * @param array $identifier
     *
     * @return RpcRequestInterface
     */
    abstract protected function createFindRequest(array $identifier);

    /**
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return RpcRequestInterface
     */
    abstract protected function createSearchRequest(
        array $criteria = [],
        array $orderBy = null,
        $limit = null,
        $offset = null
    );
}
