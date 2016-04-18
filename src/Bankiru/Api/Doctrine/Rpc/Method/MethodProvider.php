<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.01.2016
 * Time: 11:30
 */

namespace Bankiru\Api\Doctrine\Rpc\Method;

final class MethodProvider implements MethodProviderInterface
{
    private $methods = [];

    /**
     * MethodProvider constructor.
     *
     * @param string[] $methods
     */
    public function __construct(array $methods)
    {
        $this->methods = $methods;
    }

    /** {@inheritdoc} */
    public function getMethod($method)
    {
        if (!$this->hasMethod($method)) {
            throw new \OutOfBoundsException(sprintf('Method %s is not defined', $method));
        }

        return $this->methods[$method];
    }

    /**
     * @param string $method
     *
     * @return bool
     */
    public function hasMethod($method)
    {
        return array_key_exists($method, $this->methods);
    }
}
