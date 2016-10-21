<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\ClientRegistry;
use Bankiru\Api\Doctrine\ClientRegistryInterface;
use Bankiru\Api\Doctrine\Configuration;
use Bankiru\Api\Doctrine\ConstructorFactoryResolver;
use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\EntityMetadataFactory;
use Bankiru\Api\Doctrine\Mapping\Driver\YmlMetadataDriver;
use Bankiru\Api\Doctrine\Type\BaseTypeRegistry;
use Bankiru\Api\Doctrine\Type\TypeRegistry;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator;
use ScayTrase\Api\Rpc\Test\RpcMockClient;
use ScayTrase\Api\Rpc\Tests\AbstractRpcTest;

abstract class AbstractEntityManagerTest extends AbstractRpcTest
{
    const DEFAULT_CLIENT = 'test-client';
    /** @var  ClientRegistryInterface */
    private $registry;
    /** @var  ApiEntityManager */
    private $manager;
    /** @var  RpcMockClient[] */
    private $clients = [];

    /**
     * @return mixed
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @return ApiEntityManager
     */
    protected function getManager()
    {
        return $this->manager;
    }

    protected function setUp()
    {
        $this->createEntityManager($this->getClientNames());
        parent::setUp();
    }

    protected function createEntityManager($clients = [self::DEFAULT_CLIENT])
    {
        $this->registry = new ClientRegistry();
        foreach ($clients as $name) {
            $this->registry->add($name, $this->getClient($name));
        }

        $configuration = $this->createConfiguration();

        $this->manager = new EntityManager($configuration);
    }

    /**
     * @param string $name
     *
     * @return RpcMockClient
     */
    protected function getClient($name = self::DEFAULT_CLIENT)
    {
        if (!array_key_exists($name, $this->clients)) {
            $this->clients[$name] = new RpcMockClient();
        }

        return $this->clients[$name];
    }

    protected function getClientNames()
    {
        return [self::DEFAULT_CLIENT];
    }

    protected function tearDown()
    {
        foreach ($this->clients as $name => $client) {
            self::assertCount(0, $client, sprintf('Response not used for "%s" client', $name));
        }

        $this->manager = null;
        $this->clients = [];
        parent::tearDown();
    }

    /**
     * @return Configuration
     */
    protected function createConfiguration()
    {
        $configuration = new Configuration();
        $configuration->setMetadataFactory(new EntityMetadataFactory());
        $configuration->setRegistry($this->registry);
        $configuration->setTypeRegistry(new BaseTypeRegistry(new TypeRegistry()));
        $configuration->setResolver(new ConstructorFactoryResolver());
        $configuration->setProxyDir(CACHE_DIR.'/doctrine/proxy/');
        $configuration->setProxyNamespace('Bankiru\Api\Doctrine\Test\Proxy');
        $driver = new MappingDriverChain();
        $driver->addDriver(
            new YmlMetadataDriver(
                new SymfonyFileLocator(
                    [
                        __DIR__.'/../Test/Resources/config/api/' => 'Bankiru\Api\Doctrine\Test\Entity',
                    ],
                    '.api.yml',
                    DIRECTORY_SEPARATOR
                )
            ),
            'Bankiru\Api\Doctrine\Test\Entity'
        );
        $configuration->setDriver($driver);

        return $configuration;
    }
}
