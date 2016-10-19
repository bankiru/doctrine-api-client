<?php

namespace Bankiru\Api\Doctrine\ApiFactory;

use Bankiru\Api\ApiFactoryInterface;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class StaticApiFactory implements ApiFactoryInterface
{
    /** @var string */
    private $name;

    /**
     * StaticApiFactory constructor.
     *
     * @param string $name
     *
     * @throws \LogicException
     */
    public function __construct($name)
    {
        $this->name = (string)$name;
        if (!class_exists($this->name)) {
            throw new \LogicException(sprintf('Class %s does not exist', $name));
        }
        if (!in_array(StaticApiFactoryInterface::class, class_implements($name), true)) {
            throw new \LogicException(sprintf('Class %s is not a static factory', $name));
        }
    }

    public function createApi(RpcClientInterface $client, ApiMetadata $metadata)
    {
        /** @var StaticApiFactoryInterface $class */
        $class = $this->name;

        return $class::createApi($client, $metadata);
    }
}
