<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

/** @internal */
final class DoctrineApi extends SingleRequestApi
{
    const METHOD_FIND   = 'find';
    const METHOD_SEARCH = 'search';
    const METHOD_COUNT  = 'count';

    private static $params = [
        'criteria',
        'sort',
        'limit',
        'offset',
    ];

    /** {@inheritdoc} */
    protected function createCountRequest(ApiMetadata $metadata, array $parameters)
    {
        return new RpcRequest(
            $metadata->getMethodContainer()->getMethod(self::METHOD_COUNT),
            array_combine(self::$params, $parameters)
        );
    }

    /** {@inheritdoc} */
    protected function createFindRequest(ApiMetadata $metadata, array $identifier)
    {
        return new RpcRequest(
            $metadata->getMethodContainer()->getMethod(self::METHOD_FIND),
            $identifier
        );
    }

    /** {@inheritdoc} */
    protected function createSearchRequest(ApiMetadata $metadata, array $parameters)
    {
        return new RpcRequest(
            $metadata->getMethodContainer()->getMethod(self::METHOD_SEARCH),
            array_combine(self::$params, $parameters)
        );
    }
}
