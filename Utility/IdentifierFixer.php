<?php

namespace Bankiru\Api\Doctrine\Utility;

use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

final class IdentifierFixer
{
    /**
     * @param array|mixed $id
     * @param ApiMetadata $metadata
     *
     * @return array
     * @throws MappingException
     */
    public static function fixScalarId($id, ApiMetadata $metadata)
    {
        if (is_array($id)) {
            return $id;
        }

        $id = (array)$id;

        $identifiers = $metadata->getIdentifierFieldNames();
        if (count($id) !== count($identifiers)) {
            throw MappingException::invalidIdentifierStructure();
        }

        return array_combine($identifiers, $id);
    }
}
