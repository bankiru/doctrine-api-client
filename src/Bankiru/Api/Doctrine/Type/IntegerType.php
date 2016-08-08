<?php

namespace Bankiru\Api\Doctrine\Type;

class IntegerType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        return (int)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (int)$value;
    }
}
