<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 18:08
 */

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
