<?php

namespace Bankiru\Api\Doctrine\Type;

class StringType implements Type
{

    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (string)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return $value;
        }

        return (string)$value;
    }
}
