<?php

namespace Bankiru\Api\Doctrine\Type;

class DateTimeType implements Type
{
    /** {@inheritdoc} */
    public function toApiValue($value, array $options = [])
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTime) {
            $value = new \DateTime($value);
        }

        return $value->format($this->getFormat($options));
    }

    /** {@inheritdoc} */
    public function fromApiValue($value, array $options = [])
    {
        if (null === $value) {
            return null;
        }

        return \DateTime::createFromFormat($this->getFormat($options), $value);
    }

    protected function getFormat(array $options)
    {
        if (!array_key_exists('format', $options)) {
            return \DateTime::ATOM;
        }

        return $options['format'];
    }
}
