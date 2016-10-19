<?php

namespace Bankiru\Api;

use Bankiru\Api\Doctrine\ApiFactory\StaticApiFactory;

final class ConstructorFactoryResolver implements ApiFactoryResolverInterface
{
    /** @var  ApiFactoryInterface[] */
    private $factories = [];

    /** {@inheritdoc} */
    public function resolve($name)
    {
        if (!array_key_exists($name, $this->factories)) {
            $this->factories[$name] = new StaticApiFactory($name);
        }

        return $this->factories[$name];
    }
}
