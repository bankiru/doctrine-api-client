<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 02.02.2016
 * Time: 14:52
 */

namespace Bankiru\Api\Tests;

use Bankiru\Api\Test\Entity\Sub\SubEntity;
use Doctrine\Common\Proxy\Proxy;
use GuzzleHttp\Psr7\Response;

class ProxyTestAbstract extends AbstractEntityManagerTest
{
    public function testLazyProxy()
    {
        $manager = $this->getManager();

        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'          => 2,
                            'payload'     => 'test-payload',
                            'sub-payload' => 'sub-payload',
                        ],
                    ]
                )
            )
        );

        /** @var SubEntity|Proxy $entity */
        $entity = $manager->getReference(SubEntity::class, 2);

        //Test that entity is a proxy and request was not send
        self::assertInstanceOf(Proxy::class, $entity);
        self::assertFalse($entity->__isInitialized());
        self::assertGreaterThan(0, $this->getResponseMock()->count());

        //Test that we can obtain ID and request was still not sent
        self::assertEquals(2, $entity->getId());
        self::assertInstanceOf(Proxy::class, $entity);
        self::assertFalse($entity->__isInitialized());
        self::assertGreaterThan(0, $this->getResponseMock()->count());

        //Test that we can obtain data and request was sent
        self::assertInstanceOf(SubEntity::class, $entity);
        self::assertEquals('test-payload', $entity->getPayload());
        self::assertEquals('sub-payload', $entity->getSubPayload());

        //Test that we are still a Proxy object
        self::assertInstanceOf(Proxy::class, $entity);
        self::assertTrue($entity->__isInitialized());
        self::assertEquals(0, $this->getResponseMock()->count());
    }

    public function testSimpleProxy()
    {
        $repository = $this->getManager()->getRepository(SubEntity::class);

        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'          => 2,
                            'payload'     => 'test-payload',
                            'sub-payload' => 'sub-payload',
                        ],
                    ]
                )
            )
        );

        /** @var SubEntity|Proxy $entity */
        $entity = $repository->find(2);

        //Test that we can obtain data and request was sent
        self::assertInstanceOf(SubEntity::class, $entity);
        self::assertEquals(2, $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());
        self::assertEquals('sub-payload', $entity->getSubPayload());
    }

    protected function getClientNames()
    {
        return [self::DEFAULT_CLIENT, 'test-reference-client'];
    }
}
