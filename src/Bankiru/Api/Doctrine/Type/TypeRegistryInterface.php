<?php

namespace Bankiru\Api\Doctrine\Type;

use Bankiru\Api\Doctrine\Exception\TypeException;

interface TypeRegistryInterface
{
    /**
     * @param string $type
     *
     * @return Type
     * @throws TypeException
     */
    public function get($type);

    /**
     * @param string $type
     *
     * @return bool
     */
    public function has($type);

    /**
     * @param string $type
     * @param Type   $instance
     *
     * @throws TypeException
     */
    public function add($type, Type $instance);

    /**
     * @param string $type
     * @param Type   $instance
     */
    public function replace($type, Type $instance);

    /**
     * @return Type[]
     */
    public function all();
}
