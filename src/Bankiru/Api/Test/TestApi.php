<?php

namespace Bankiru\Api\Test;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Bankiru\Api\Doctrine\Rpc\RpcRequest;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class TestApi implements CrudsApiInterface
{
    /** {@inheritdoc} */
    public function count(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        $request = new RpcRequest('count', $parameters);

        return (int)$client->invoke($request)->getResponse($request)->getBody();
    }

    /** {@inheritdoc} */
    public function create(RpcClientInterface $client, ApiMetadata $metadata, array $data)
    {
        $request = new RpcRequest('create', $data);

        return $client->invoke($request)->getResponse($request)->getBody();
    }

    /** {@inheritdoc} */
    public function find(RpcClientInterface $client, ApiMetadata $metadata, array $identifier)
    {
        $request = new RpcRequest('find', $identifier);

        return $client->invoke($request)->getResponse($request)->getBody();
    }

    /** {@inheritdoc} */
    public function patch(RpcClientInterface $client, ApiMetadata $metadata, array $data, array $fields)
    {
        $request = new RpcRequest('patch', array_intersect_key($data, array_flip($fields)));

        return $client->invoke($request)->getResponse($request)->isSuccessful();
    }

    /** {@inheritdoc} */
    public function search(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        $request = new RpcRequest('search', $parameters);

        return new \ArrayIterator($client->invoke($request)->getResponse($request)->getBody());
    }

    /** {@inheritdoc} */
    public function remove(RpcClientInterface $client, ApiMetadata $metadata, array $identifier)
    {
        $request = new RpcRequest('remove', $identifier);

        return $client->invoke($request)->getResponse($request)->isSuccessful();
    }
}
