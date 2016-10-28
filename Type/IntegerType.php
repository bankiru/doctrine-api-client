<?php

namespace Bankiru\Api\Doctrine\Type;

class IntegerType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (int)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (int)$value;
    }
}
