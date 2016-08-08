<?php

namespace Bankiru\Api\Doctrine\Rpc\Method;

final class EntityMethodProvider implements MethodProviderInterface
{
    const DEFAULT_PATH_SEPARATOR = '/';

    /** @var  string */
    private $entityPath;
    /** @var  string */
    private $pathSeparator;
    /** @var MethodProviderInterface */
    private $provider;

    /**
     * EntityMethodProvider constructor.
     *
     * @param string                  $entityPath
     * @param string                  $pathSeparator
     * @param MethodProviderInterface $chainedProvider
     */
    public function __construct(
        $entityPath,
        $pathSeparator = self::DEFAULT_PATH_SEPARATOR,
        MethodProviderInterface $chainedProvider = null
    ) {
        $this->entityPath    = $entityPath;
        $this->pathSeparator = (string)$pathSeparator;
        $this->provider      = $chainedProvider;
        if (null === $this->provider) {
            $this->provider = new MethodProvider([]);
        }
    }

    /**
     * @param string $method
     *
     * @return string
     * @throws \OutOfBoundsException
     */
    public function getMethod($method)
    {
        if ($this->provider->hasMethod($method)) {
            return $this->provider->getMethod($method);
        }

        return $this->entityPath.$this->pathSeparator.$method;
    }

    /**
     * @param string $method
     *
     * @return bool
     */
    public function hasMethod($method)
    {
        return true;
    }
}
