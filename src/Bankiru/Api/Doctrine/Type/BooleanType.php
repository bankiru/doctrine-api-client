<?php

namespace Bankiru\Api\Doctrine\Type;

class BooleanType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        return (bool)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (bool)$value;
    }
}
