<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 18:13
 */

namespace Bankiru\Api\Doctrine\Type;

class ArrayType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        return (array)$value;
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        return (array)$value;
    }
}
