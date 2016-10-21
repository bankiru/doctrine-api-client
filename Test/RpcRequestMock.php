<?php

namespace Bankiru\Api\Doctrine\Test;

use ScayTrase\Api\Rpc\RpcRequestInterface;

/**
 * @internal
 */
// private
final class RpcRequestMock implements RpcRequestInterface
{
    /** @var  string */
    private $method;
    /** @var  array */
    private $parameters;

    /**
     * RpcRequestMock constructor.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __construct($method, array $parameters = [])
    {
        $this->method     = (string)$method;
        $this->parameters = $parameters;
    }

    /** {@inheritdoc} */
    public function getMethod()
    {
        return $this->method;
    }

    /** {@inheritdoc} */
    public function getParameters()
    {
        return $this->parameters;
    }
}
