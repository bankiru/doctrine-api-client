<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 17:55
 */

namespace Bankiru\Api\Doctrine\Type;

class TypeRegistry implements TypeRegistryInterface
{
    /** @var  Type[] */
    private $types = [];

    /** {@inheritdoc} */
    public function get($type)
    {
        if (!$this->has($type)) {
            throw new \OutOfBoundsException('No type ' . $type . ' found');
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
            throw new \LogicException('Type ' . $type . ' already present');
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
