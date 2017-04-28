<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\ApiFactory\ApiFactoryRegistryFactory;
use Bankiru\Api\Doctrine\ApiFactory\ChainApiFactoryRegistry;
use Bankiru\Api\Doctrine\ApiFactory\StaticApiFactoryFactory;
use Bankiru\Api\Doctrine\ApiFactoryRegistryInterface;
use Bankiru\Api\Doctrine\ClientRegistry;
use Bankiru\Api\Doctrine\ClientRegistryInterface;
use Bankiru\Api\Doctrine\Configuration;
use Bankiru\Api\Doctrine\EntityManager;
use Bankiru\Api\Doctrine\EntityMetadataFactory;
use Bankiru\Api\Doctrine\Mapping\Driver\YmlMetadataDriver;
use Bankiru\Api\Doctrine\Test\TestApiFactory;
use Bankiru\Api\Doctrine\Type\BaseTypeRegistry;
use Bankiru\Api\Doctrine\Type\TypeRegistry;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator;
use PHPUnit\Framework\TestCase;
use ScayTrase\Api\Rpc\Test\RpcMockClient;
use ScayTrase\Api\Rpc\Tests\AbstractRpcTest;
use ScayTrase\Api\Rpc\Tests\RpcRequestTrait;

abstract class AbstractEntityManagerTest extends TestCase
{
    use RpcRequestTrait;

    const DEFAULT_CLIENT = 'test-client';
    /** @var  ClientRegistryInterface */
    private $clientRegistry;
    /** @var  ApiEntityManager */
    private $manager;
    /** @var  RpcMockClient[] */
    private $clients = [];
    /** @var  ApiFactoryRegistryInterface */
    private $factoryRegistry;

    /**
     * @return mixed
     */
    public function getClientRegistry()
    {
        return $this->clientRegistry;
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
        $this->clientRegistry = new ClientRegistry();
        foreach ($clients as $name) {
            $this->clientRegistry->add($name, $this->getClient($name));
        }

        $this->factoryRegistry = new ChainApiFactoryRegistry();

        $factory = new ApiFactoryRegistryFactory();
        foreach ($this->getFactoryApis() as $name => $api) {
            $factory->set($name, $api);
        }
        $this->factoryRegistry->add($factory);
        $this->factoryRegistry->add(new StaticApiFactoryFactory());

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
        $configuration->setClientRegistry($this->clientRegistry);
        $configuration->setTypeRegistry(new BaseTypeRegistry(new TypeRegistry()));
        $configuration->setFactoryRegistry($this->factoryRegistry);
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

    protected function getFactoryApis()
    {
        return [];
    }
}
