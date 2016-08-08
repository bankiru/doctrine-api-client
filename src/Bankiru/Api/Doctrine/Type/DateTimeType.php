<?php

namespace Bankiru\Api\Doctrine\Type;

class DateTimeType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (!$value instanceof \DateTime) {
            $value = new \DateTime($value);
        }

        return $value->format('c');
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return null;
        }

        return \DateTime::createFromFormat('c', $value);
    }
}
