<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 17.03.2016
 * Time: 13:59
 */

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
