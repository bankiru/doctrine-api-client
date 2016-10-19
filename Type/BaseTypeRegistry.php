<?php

namespace Bankiru\Api\Doctrine\Type;

class BaseTypeRegistry implements TypeRegistryInterface
{
    /** @var  TypeRegistryInterface */
    private $registry;

    /**
     * BaseTypeRegistry constructor.
     *
     * @param TypeRegistryInterface $registry
     */
    public function __construct(TypeRegistryInterface $registry)
    {
        $this->registry = $registry;

        $this->registry->add('string', new StringType());
        $this->registry->add('text', new StringType());
        $this->registry->add('integer', new IntegerType());
        $this->registry->add('int', new IntegerType());
        $this->registry->add('float', new FloatType());
        $this->registry->add('bool', new BooleanType());
        $this->registry->add('boolean', new BooleanType());
        $this->registry->add('array', new ArrayType());
        $this->registry->add('datetime', new DateTimeType());
        $this->registry->add('timestamp', new TimeStampType());
    }

    /** {@inheritdoc} */
    public function get($type)
    {
        return $this->registry->get($type);
    }

    /** {@inheritdoc} */
    public function has($type)
    {
        return $this->registry->has($type);
    }

    /** {@inheritdoc} */
    public function add($type, Type $instance)
    {
        return $this->registry->add($type, $instance);
    }

    /** {@inheritdoc} */
    public function replace($type, Type $instance)
    {
        return $this->registry->replace($type, $instance);
    }

    /** {@inheritdoc} */
    public function all()
    {
        return $this->registry->all();
    }
}
