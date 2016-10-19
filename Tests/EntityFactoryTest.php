<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\CompositeKeyEntity;
use Bankiru\Api\Doctrine\Test\Entity\Sub\SubEntity;
use Bankiru\Api\Doctrine\Test\Entity\TestEntity;
use GuzzleHttp\Psr7\Response;

class EntityFactoryTest extends AbstractEntityManagerTest
{
    protected function getClientNames()
    {
        return array_merge(parent::getClientNames(), ['test-reference-client']);
    }


    public function testEntityLoading()
    {
        $repository = $this->getManager()->getRepository(TestEntity::class);

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

        /** @var TestEntity $entity */
        $entity = $repository->find(1);

        self::assertInstanceOf(TestEntity::class, $entity);
        self::assertEquals(1, $entity->getId());
        self::assertInternalType('int', $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());
    }

    public function testInheritanceLoading()
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

        /** @var SubEntity $entity */
        $entity = $repository->find(2);

        self::assertInstanceOf(SubEntity::class, $entity);
        self::assertEquals(2, $entity->getId());
        self::assertEquals('test-payload', $entity->getPayload());
        self::assertEquals('sub-payload', $entity->getSubPayload());
    }

    public function testCompositeKeyLoading()
    {
        $repository = $this->getManager()->getRepository(CompositeKeyEntity::class);
        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'first_key'  => 2,
                            'second_key' => 'test',
                            'payload'    => 'test-payload',
                        ],
                    ]
                )
            )
        );

        /** @var CompositeKeyEntity $entity */
        $entity = $repository->find(['firstKey' => 2, 'secondKey' => 'test']);

        self::assertInstanceOf(CompositeKeyEntity::class, $entity);
        self::assertEquals(2, $entity->getFirstKey());
        self::assertEquals('test', $entity->getSecondKey());
        self::assertEquals('test-payload', $entity->getPayload());
    }
}
