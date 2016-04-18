<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 18:11
 */

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
