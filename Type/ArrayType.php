<?php

namespace Bankiru\Api\Doctrine\Type;

class ArrayType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return json_decode(json_encode($value), true);
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return json_decode(json_encode($value), true);
    }
}
