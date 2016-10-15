<?php

namespace Bankiru\Api\Tests;

use Bankiru\Api\Test\Entity\CustomEntity;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class EntityCacheTest extends AbstractEntityManagerTest
{
    private static $cache;

    public function testEntityCache()
    {
        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'      => '1',
                            'payload' => 'test-payload',
                        ],
                    ]
                )
            )
        );

        $repository = $this->getManager()->getRepository(CustomEntity::class);
        /** @var CustomEntity $entity */
        $entity = $repository->find(1);

        self::assertInstanceOf(CustomEntity::class, $entity);
        self::assertEquals(1, $entity->getId());
        self::assertInternalType('int', $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());

        $this->createEntityManager($this->getClientNames());

        $repository = $this->getManager()->getRepository(CustomEntity::class);
        /** @var CustomEntity $entity */
        $entity = $repository->find(1);

        self::assertInstanceOf(CustomEntity::class, $entity);
        self::assertEquals(1, $entity->getId());
        self::assertInternalType('int', $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());

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
        $cache->getItem(Argument::type('string'))->will(function ($args) use (&$items, $that) {
            $key = $args[0];
            if (!array_key_exists($key, $items)) {
                $item = $that->prophesize(CacheItemInterface::class);
                $item->getKey()->willReturn($key);
                $item->isHit()->willReturn(false);
                $item->get()->willReturn(null);
                $item->set(Argument::any())->will(function ($args) use ($item) {
                    $item->get()->willReturn($args[0]);

                    return $item;
                });
                $item->expiresAfter(Argument::type('int'))->willReturn($item);
                $item->expiresAfter(Argument::exact(null))->willReturn($item);
                $item->expiresAfter(Argument::type(\DateInterval::class))->willReturn($item);
                $item->expiresAt(Argument::type(\DateTimeInterface::class))->willReturn($item);
                $items[$key] = $item;
            }

            return $items[$key]->reveal();
        });
        $cache->save(Argument::type(CacheItemInterface::class))->will(function ($args) use (&$items) {
            $item = $args[0];
            $items[$item->getKey()]->isHit()->willReturn(true);
        });

        return $cache->reveal();
    }
}
