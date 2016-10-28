<?php

namespace Bankiru\Api\Doctrine\Type;

class FloatType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (float)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (float)$value;
    }
}
