<?php

namespace Bankiru\Api\Doctrine\Type;

class StringType implements Type
{

    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        return (string)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (string)$value;
    }
}
