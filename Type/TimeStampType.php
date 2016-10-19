<?php

namespace Bankiru\Api\Doctrine\Type;

class TimeStampType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (!$value instanceof \DateTime) {
            $value = new \DateTime($value);
        }

        return $value->getTimestamp();
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (new \DateTime())->setTimestamp($value);
    }
}
