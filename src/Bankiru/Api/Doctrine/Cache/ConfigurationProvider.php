<?php

namespace Bankiru\Api\Doctrine\Cache;

use Bankiru\Api\Doctrine\CacheConfigurationProvider;
use Bankiru\Api\Doctrine\Configuration;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;

final class ConfigurationProvider implements CacheConfigurationProvider
{
    /** @var  Configuration */
    private $configuration;

    /**
     * ConfigurationProvider constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /** {@inheritdoc} */
    public function getCacheConfiguration(ApiMetadata $metadata)
    {
        return $this->configuration->getCacheConfiguration($metadata->getName());
    }
}
