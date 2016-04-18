<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.01.2016
 * Time: 11:24
 */

namespace Bankiru\Api\Doctrine\Rpc;

use ScayTrase\Api\Rpc\RpcRequestInterface;

/**
 * Class RpcRequest
 *
 * @package Bankiru\Api\Doctrine\Rpc
 * @internal
 */
// private
final class RpcRequest implements RpcRequestInterface
{
    /** @var  string */
    private $method;
    /** @var  array */
    private $parameters;

    /**
     * RpcRequest constructor.
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
