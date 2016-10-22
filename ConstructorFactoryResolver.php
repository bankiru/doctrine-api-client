<?php

namespace Bankiru\Api\Doctrine;

use Bankiru\Api\Doctrine\ApiFactory\StaticApiFactoryFactory;

final class ConstructorFactoryResolver implements ApiFactoryResolverInterface
{
    /** @var  ApiFactoryInterface[] */
    private $factories = [];

    /** {@inheritdoc} */
    public function resolve($name)
    {
        if (!array_key_exists($name, $this->factories)) {
            $this->factories[$name] = new StaticApiFactoryFactory($name);
        }

        return $this->factories[$name];
    }
}
