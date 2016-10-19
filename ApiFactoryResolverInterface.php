<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\Exception\MappingException;

interface ApiFactoryResolverInterface
{
    /**
     * Resolves string identifier into real API factory
     *
     * @param string $name
     *
     * @return ApiFactoryInterface
     * @throws MappingException
     */
    public function resolve($name);
}
