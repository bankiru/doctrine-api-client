<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Exception\ApiCallException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;
use ScayTrase\Api\Rpc\RpcRequestInterface;

/** @internal */
abstract class SingleRequestApi implements Counter, Searcher, Finder
{
    /** {@inheritdoc} */
    public function count(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        $request  = $this->createCountRequest($metadata, $parameters);
        $response = $client->invoke($request)->getResponse($request);

        if (!$response->isSuccessful()) {
            throw ApiCallException::callFailed($response);
        }

        return (int)$response->getBody();
    }

    /** {@inheritdoc} */
    public function find(RpcClientInterface $client, ApiMetadata $metadata, array $identifier)
    {
        $request  = $this->createFindRequest($metadata, $identifier);
        $response = $client->invoke([$request])->getResponse($request);

        if (!$response->isSuccessful()) {
            throw ApiCallException::callFailed($response);
        }

        return $response->getBody();
    }

    /** {@inheritdoc} */
    public function search(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        $request  = $this->createSearchRequest($metadata, $parameters);
        $response = $client->invoke($request)->getResponse($request);

        if (!$response->isSuccessful()) {
            throw ApiCallException::callFailed($response);
        }

        return new \ArrayIterator($response->getBody());
    }

    /**
     * @param ApiMetadata $metadata
     * @param array       $criteria
     *
     * @return RpcRequestInterface
     */
    abstract protected function createCountRequest(ApiMetadata $metadata, array $criteria);

    /**
     * @param ApiMetadata $metadata
     * @param array       $identifier
     *
     * @return RpcRequestInterface
     */
    abstract protected function createFindRequest(ApiMetadata $metadata, array $identifier);

    /**
     * @param ApiMetadata $metadata
     * @param array       $parameters
     *
     * @return RpcRequestInterface
     */
    abstract protected function createSearchRequest(ApiMetadata $metadata, array $parameters);
}
