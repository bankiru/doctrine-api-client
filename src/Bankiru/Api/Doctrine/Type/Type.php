<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 16.03.2016
 * Time: 17:53
 */

namespace Bankiru\Api\Doctrine\Type;

interface Type
{
    /**
     * @param mixed $value
     * @param array $options
     *
     * @return mixed
     */
    public function toApiValue($value, array $options = []);

    /**
     * @param mixed $value
     * @param array $options
     *
     * @return mixed
     */
    public function fromApiValue($value, array $options = []);
}
