<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 18:14
 */

namespace Bankiru\Api\Doctrine\Type;


class FloatType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        return (float)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (float)$value;
    }
}
