<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 18:14
 */

namespace Bankiru\Api\Doctrine\Type;

class DateTimeType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (! $value instanceof \DateTime) {
            $value = new \DateTime($value);
        }

        return $value->format('c');
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return \DateTime::createFromFormat('c', $value);
    }
}
