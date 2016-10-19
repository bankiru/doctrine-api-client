<?php

namespace Bankiru\Api\Doctrine\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use ScayTrase\Api\IdGenerator\IdGeneratorInterface;
use ScayTrase\Api\JsonRpc\JsonRpcClient;
use ScayTrase\Api\Rpc\Exception\RpcExceptionInterface;
use ScayTrase\Api\Rpc\ResponseCollectionInterface;
use ScayTrase\Api\Rpc\RpcClientInterface;
use ScayTrase\Api\Rpc\RpcRequestInterface;

class TestClient implements RpcClientInterface
{
    /** @var  JsonRpcClient */
    private $client;
    /** @var  IdGeneratorInterface */
    private $idGenerator;

    /**
     * TestClient constructor.
     *
     * @param MockHandler          $mock
     * @param IdGeneratorInterface $idGenerator
     */
    public function __construct(MockHandler $mock, IdGeneratorInterface $idGenerator)
    {
        $handler           = HandlerStack::create($mock);
        $this->idGenerator = $idGenerator;
        $guzzle            = new Client(['handler' => $handler]);
        $this->client      = new JsonRpcClient($guzzle, new Uri('http://localhost/'), $this->idGenerator);
    }

    /**
     * @param RpcRequestInterface|RpcRequestInterface[] $calls
     *
     * @return ResponseCollectionInterface
     *
     * @throws RpcExceptionInterface
     */
    public function invoke($calls)
    {
        return $this->client->invoke($calls);
    }
}
