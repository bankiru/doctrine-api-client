<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

final class ScalarKeyStrategy implements KeyStrategyInterface
{
    /** {@inheritdoc} */
    public function getEntityPrefix(ApiMetadata $metadata)
    {
        return str_replace('\\', '__', $metadata->getName());
    }

    /** {@inheritdoc} */
    public function getEntityKey(ApiMetadata $metadata, array $identifier)
    {
        foreach ($metadata->getIdentifierFieldNames() as $name) {
            if ($metadata->hasAssociation($name)) {
                throw new \LogicException('Invalid strategy for relation-based identifier');
            }
        }

        return $this->getEntityPrefix($metadata) . '_' . sha1(json_encode($identifier));
    }
}
