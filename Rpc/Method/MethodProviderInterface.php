<?php

namespace Bankiru\Api\Doctrine\Rpc\Method;

interface MethodProviderInterface
{
    /**
     * @param string $method
     *
     * @return string
     * @throws \OutOfBoundsException
     */
    public function getMethod($method);

    /**
     * @param string $method
     *
     * @return bool
     */
    public function hasMethod($method);
}
