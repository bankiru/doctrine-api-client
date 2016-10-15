<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Utility\IdentifierFlattener;

final class Sha1Strategy implements KeyStrategyInterface
{
    /** @var  IdentifierFlattener */
    private $flattener;
    /**
     * @var string
     */
    private $prefix;

    /**
     * Sha1Strategy constructor.
     *
     * @param IdentifierFlattener $flattener
     * @param string              $prefix
     */
    public function __construct(IdentifierFlattener $flattener, $prefix = '')
    {
        $this->flattener = $flattener;
        $this->prefix    = (string)$prefix;
    }


    /** {@inheritdoc} */
    public function getEntityPrefix(ApiMetadata $metadata)
    {
        return $this->prefix;
    }

    /** {@inheritdoc} */
    public function getEntityKey(ApiMetadata $metadata, $identifier)
    {
        $flattenIdentifiers = $this->flattener->flattenIdentifier($metadata, $identifier);

        return sha1(sprintf('%s %s', $metadata->getName(), implode(' ', $flattenIdentifiers)));
    }
}
