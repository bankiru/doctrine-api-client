<?php

namespace Bankiru\Api\Doctrine\Type;

class BooleanType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (bool)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (bool)$value;
    }
}
