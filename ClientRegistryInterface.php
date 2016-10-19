<?php

namespace Bankiru\Api\Doctrine;

use ScayTrase\Api\Rpc\RpcClientInterface;

interface ClientRegistryInterface
{
    /**
     * @param string $name
     *
     * @return bool
     */
    public function has($name);

    /**
     * @param string $name
     *
     * @return RpcClientInterface
     * @throws \OutOfBoundsException
     */
    public function get($name);

    /**
     * @param string             $name
     * @param RpcClientInterface $client
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function add($name, RpcClientInterface $client);

    /**
     * @param string             $name
     * @param RpcClientInterface $client
     *
     * @return void
     */
    public function replace($name, RpcClientInterface $client);

    /**
     * @return RpcClientInterface[]
     */
    public function all();
}
