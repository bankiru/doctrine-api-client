<?php

namespace Bankiru\Api\Doctrine\Cache;

use Symfony\Component\OptionsResolver\OptionsResolver;

class CacheConfiguration implements CacheConfigurationInterface
{
    /** @var int|null */
    private $ttl;
    /** @var bool */
    private $enabled = false;
    /** @var KeyStrategyInterface */
    private $strategy;
    /** @var  array */
    private $extra;

    private function __construct()
    {
    }

    /** {@inheritdoc} */
    public function getStrategy()
    {
        return $this->enabled ? $this->strategy : null;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public static function create(array $data)
    {
        $resolver = new OptionsResolver();
        self::configureResolver($resolver);
        $data = $resolver->resolve($data);

        $configuration = new static();

        $configuration->enabled  = $data['enabled'];
        $configuration->strategy = $data['strategy'];
        $configuration->ttl      = $data['ttl'];

        $configuration->extra = $data['extra'] ?: [];

        return $configuration;
    }

    /**
     * Constructor for disabled cache entities configuration
     *
     * @return static
     */
    public static function disabled()
    {
        $configuration          = new static();
        $configuration->enabled = false;

        return $configuration;
    }

    /** {@inheritdoc} */
    public function getTtl()
    {
        return $this->enabled ? $this->ttl : null;
    }

    /** {@inheritdoc} */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /** {@inheritdoc} */
    public function extra($key)
    {
        if (!$this->enabled) {
            return null;
        }

        if (!array_key_exists($key, $this->extra)) {
            return null;
        }

        return $this->extra[$key];
    }

    private static function configureResolver(OptionsResolver $resolver)
    {
        $resolver->setDefault('enabled', false);
        $resolver->setAllowedValues('enabled', [false, true]);
        $resolver->setDefault('ttl', null);
        $resolver->setAllowedTypes('ttl', ['int', 'null']);
        $resolver->setDefault('strategy', new ScalarKeyStrategy());
        $resolver->setAllowedTypes('strategy', KeyStrategyInterface::class);
        $resolver->setDefault('extra', []);
        $resolver->setAllowedTypes('extra', ['array', null]);
    }
}
