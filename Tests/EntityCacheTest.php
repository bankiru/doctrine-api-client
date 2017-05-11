<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\CustomEntity;
use Bankiru\Api\Doctrine\Test\Entity\CustomEntityInheritor;
use Bankiru\Api\Doctrine\Test\Entity\TestEntity;
use Prophecy\Argument;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use ScayTrase\Api\Rpc\RpcRequestInterface;

final class EntityCacheTest extends AbstractEntityManagerTest
{
    private static $cache;

    public function testEntityCache()
    {
        $this->getClient()->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'      => '1',
                    'payload' => 'test-payload',
                ]
            ),
            function (RpcRequestInterface $request) {
                self::assertEquals('custom-entity/find', $request->getMethod());
                self::assertEquals(['id' => 1], $request->getParameters());

                return true;
            }
        );

        self::assertCount(1, $this->getClient());
        $repository = $this->getManager()->getRepository(CustomEntityInheritor::class);
        /** @var CustomEntity $entity */
        $entity = $repository->find(1);
        self::assertCount(0, $this->getClient());
        self::assertInstanceOf(CustomEntityInheritor::class, $entity);
        self::assertEquals(1, $entity->getId());
        self::assertInternalType('int', $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());

        $this->createEntityManager($this->getClientNames());

        $repository = $this->getManager()->getRepository(CustomEntityInheritor::class);
        /** @var CustomEntity $entity */
        $entity = $repository->find(1);

        self::assertInstanceOf(CustomEntityInheritor::class, $entity);
        self::assertEquals(1, $entity->getId());
        self::assertInternalType('int', $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());

        $inheritorConfiguration = $this->getManager()->getConfiguration()->getCacheConfiguration(CustomEntityInheritor::class);
        self::assertTrue($inheritorConfiguration->extra('quick_search'));
        $parentConfiguration = $this->getManager()->getConfiguration()->getCacheConfiguration(CustomEntity::class);
        self::assertSame($inheritorConfiguration, $parentConfiguration);
    }

    protected function createConfiguration()
    {
        $configuration = parent::createConfiguration();

        $log = $this->prophesize(LoggerInterface::class);
        $log->debug(Argument::any(), Argument::any())->shouldBeCalled();

        $configuration->setApiCache($this->getCache());
        $configuration->setApiCacheLogger($log->reveal());
        $configuration->setCacheConfiguration(
            CustomEntity::class,
            [
                'ttl'     => 900,
                'enabled' => true,
                'extra'   => ['quick_search' => true],
            ]
        );

        return $configuration;
    }

    /**
     * @return mixed
     */
    private function getCache()
    {
        if (null === self::$cache) {
            self::$cache = $this->createCache();
        }

        return self::$cache;
    }

    /**
     * @return CacheItemPoolInterface
     * @throws \LogicException
     *
     * @link https://gist.github.com/scaytrase/3cf9c5ece4218280669c
     */
    private function createCache()
    {
        static $items = [];
        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $that  = $this;
        $cache->getItem(Argument::type('string'))->will(
            function ($args) use (&$items, $that) {
                $key = $args[0];
                if (!array_key_exists($key, $items)) {
                    $item = $that->prophesize(CacheItemInterface::class);
                    $item->getKey()->willReturn($key);
                    $item->isHit()->willReturn(false);
                    $item->get()->willReturn(null);
                    $item->set(Argument::any())->will(
                        function ($args) use ($item) {
                            $item->get()->willReturn($args[0]);

                            return $item;
                        }
                    );
                    $item->expiresAfter(Argument::type('int'))->willReturn($item);
                    $item->expiresAfter(Argument::exact(null))->willReturn($item);
                    $item->expiresAfter(Argument::type(\DateInterval::class))->willReturn($item);
                    $item->expiresAt(Argument::type(\DateTimeInterface::class))->willReturn($item);
                    $items[$key] = $item;
                }

                return $items[$key]->reveal();
            }
        );
        $cache->save(Argument::type(CacheItemInterface::class))->will(
            function ($args) use (&$items) {
                $item = $args[0];
                $items[$item->getKey()]->isHit()->willReturn(true);
            }
        );

        return $cache->reveal();
    }
}
