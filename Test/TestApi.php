<?php

namespace Bankiru\Api\Doctrine\Test;

use Bankiru\Api\Doctrine\ApiFactory\StaticApiFactoryInterface;
use Bankiru\Api\Doctrine\Cache\EntityCacheAwareInterface;
use Bankiru\Api\Doctrine\Cache\VoidEntityCache;
use Bankiru\Api\Doctrine\EntityDataCacheInterface;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Rpc\CrudsApiInterface;
use Bankiru\Api\Doctrine\Rpc\RpcRequest;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class TestApi implements CrudsApiInterface, StaticApiFactoryInterface, EntityCacheAwareInterface
{
    /** @var RpcClientInterface */
    private $client;
    /** @var ApiMetadata */
    private $metadata;
    /** @var EntityDataCacheInterface */
    private $cache;

    /**
     * TestApi constructor.
     *
     * @param RpcClientInterface $client
     * @param ApiMetadata        $metadata
     */
    public function __construct(RpcClientInterface $client, ApiMetadata $metadata)
    {
        $this->client   = $client;
        $this->metadata = $metadata;
        $this->cache    = new VoidEntityCache($metadata);
    }

    public static function createApi(RpcClientInterface $client, ApiMetadata $metadata)
    {
        return new static($client, $metadata);
    }

    /** {@inheritdoc} */
    public function count(array $criteria)
    {
        $request = new RpcRequest('count', $criteria);

        return (int)$this->client->invoke($request)->getResponse($request)->getBody();
    }

    /** {@inheritdoc} */
    public function create(array $data)
    {
        $request = new RpcRequest('create', $data);

        return $this->client->invoke($request)->getResponse($request)->getBody();
    }

    /** {@inheritdoc} */
    public function find(array $identifier)
    {
        $body = $this->cache->get($identifier);

        if (null !== $body) {
            return $body;
        }

        $request = new RpcRequest('find', $identifier);
        $body = $this->client->invoke($request)->getResponse($request)->getBody();
        $this->cache->set($identifier, $body);

        return $body;
    }

    /** {@inheritdoc} */
    public function patch(array $identifier, array $data, array $fields)
    {
        $request = new RpcRequest('patch', array_intersect_key($data, array_flip($fields)));

        return $this->client->invoke($request)->getResponse($request)->isSuccessful();
    }

    /** {@inheritdoc} */
    public function search(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $request = new RpcRequest(
            'search',
            [
                'criteria' => $criteria,
                'order'    => $orderBy,
                'limit'    => $limit,
                'offset'   => $offset,
            ]
        );

        return new \ArrayIterator($this->client->invoke($request)->getResponse($request)->getBody());
    }

    /** {@inheritdoc} */
    public function remove(array $identifier)
    {
        $request = new RpcRequest('remove', $identifier);

        return $this->client->invoke($request)->getResponse($request)->isSuccessful();
    }

    /** @return RpcClientInterface */
    public function getClient()
    {
        return $this->client;
    }

    /** @return ApiMetadata */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /** {@inheritdoc} */
    public function setEntityCache(EntityDataCacheInterface $cache)
    {
        $this->cache = $cache;
    }
}
