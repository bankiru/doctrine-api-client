<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 17:55
 */

namespace Bankiru\Api\Doctrine\Type;

use Bankiru\Api\Doctrine\Exception\TypeException;

class TypeRegistry implements TypeRegistryInterface
{
    /** @var  Type[] */
    private $types = [];

    /** {@inheritdoc} */
    public function get($type)
    {
        if (!$this->has($type)) {
            throw TypeException::unknown($type);
        }

        return $this->types[$type];
    }

    /** {@inheritdoc} */
    public function has($type)
    {
        return array_key_exists($type, $this->types);
    }

    /** {@inheritdoc} */
    public function add($type, Type $instance)
    {
        if ($this->has($type)) {
            throw TypeException::cannotRedeclare($type);
        }

        $this->replace($type, $instance);
    }

    /** {@inheritdoc} */
    public function replace($type, Type $instance)
    {
        $this->types[$type] = $instance;
    }

    /** {@inheritdoc} */
    public function all()
    {
        return $this->types;
    }
}
