<?php

namespace Bankiru\Api\Doctrine;

use ScayTrase\Api\Rpc\RpcClientInterface;

final class ClientRegistry implements ClientRegistryInterface
{
    /** @var  RpcClientInterface[] */
    private $clients = [];

    /**
     * @param string $name
     *
     * @return RpcClientInterface
     * @throws \OutOfBoundsException
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new \OutOfBoundsException(sprintf('Client "%s" not registered', $name));
        }

        return $this->clients[$name];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->clients);
    }

    /**
     * @param string             $name
     * @param RpcClientInterface $client
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function add($name, RpcClientInterface $client)
    {
        if ($this->has($name)) {
            throw new \InvalidArgumentException();
        }

        $this->replace($name, $client);
    }

    /**
     * @param string             $name
     * @param RpcClientInterface $client
     *
     * @return void
     */
    public function replace($name, RpcClientInterface $client)
    {
        $this->clients[$name] = $client;
    }

    /**
     * @return RpcClientInterface[]
     */
    public function all()
    {
        return $this->clients;
    }
}
