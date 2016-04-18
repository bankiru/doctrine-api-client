<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.01.2016
 * Time: 11:31
 */

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
