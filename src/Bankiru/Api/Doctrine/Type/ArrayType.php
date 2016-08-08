<?php

namespace Bankiru\Api\Doctrine\Type;

class ArrayType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        return (array)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (array)$value;
    }
}
