<?php

namespace Bankiru\Api\Tests;

use Bankiru\Api\Test\Entity\Sub\SubEntity;
use Bankiru\Api\Test\Entity\TestEntity;
use Bankiru\Api\Test\Entity\TestReference;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use GuzzleHttp\Psr7\Response;

class ReferenceLoadingTest extends AbstractEntityManagerTest
{
    public function testAnyToOneLoading()
    {

        $repository = $this->getManager()->getRepository(TestEntity::class);

        $this->getResponseMock('test-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'         => '1',
                            'payload'    => 'test-payload',
                            'references' => [],
                            'parent'     => '2',
                        ],
                    ]
                )
            )
        );

        $this->getResponseMock('test-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'         => '2',
                            'payload'    => 'parent-payload',
                            'references' => [],
                            'parent'     => 1,
                        ],
                    ]
                )
            )
        );


        /** @var TestEntity|Proxy $entity */
        $entity = $repository->find(1);
        $parent = $entity->getParent();

        self::assertInstanceOf(TestEntity::class, $parent);
        self::assertEquals('parent-payload', $parent->getPayload());
        self::assertEquals(2, $parent->getId());
        self::assertInternalType('int', $parent->getId());

        self::assertEquals($entity, $parent->getParent());
    }

    public function testOneToManyLoading()
    {
        $repository = $this->getManager()->getRepository(TestEntity::class);

        $this->getResponseMock('test-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'         => '1',
                            'payload'    => 'test-payload',
                            'references' => ['5', '7'],
                        ],
                    ]
                )
            )
        );

        $this->getResponseMock('test-reference-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            [
                                'id'                => '5',
                                'reference-payload' => 'test-payload-5',
                                'owner'             => '1',
                            ],
                            [
                                'id'                => '7',
                                'reference-payload' => 'test-payload-7',
                                'owner'             => '1',
                            ],
                        ],
                    ]
                )
            )
        );


        /** @var TestEntity $entity */
        $entity = $repository->find(1);

        self::assertInstanceOf(TestEntity::class, $entity);
        self::assertEquals('test-payload', $entity->getPayload());
        self::assertEquals(1, $entity->getId());
        self::assertInstanceOf(\Countable::class, $entity->getReferences());
        self::assertEquals(1, $this->getResponseMock('test-reference-client')->count());
        self::assertCount(2, $entity->getReferences());
        self::assertEquals(0, $this->getResponseMock('test-reference-client')->count());
        self::assertInstanceOf(Collection::class, $entity->getReferences());


        foreach ($entity->getReferences() as $reference) {
            self::assertInternalType('int', $reference->getId());
            self::assertInternalType('int', $reference->getOwner()->getId());
            self::assertInstanceOf(TestReference::class, $reference);
            self::assertEquals('test-payload-' . $reference->getId(), $reference->getReferencePayload());
            self::assertEquals($entity, $reference->getOwner());
        }
    }

    public function testFindByReference()
    {
        $repository = $this->getManager()->getRepository(TestEntity::class);

        $this->getResponseMock('test-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'         => 1,
                            'payload'    => 'test-payload',
                            'references' => [],
                        ],
                    ]
                )
            )
        );

        /** @var TestEntity $parent */
        $parent = $repository->find(1);
        self::assertInstanceOf(TestEntity::class, $parent);
        self::assertEquals('test-payload', $parent->getPayload());
        self::assertEquals(1, $parent->getId());

        $this->getResponseMock('test-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            [
                                'id'         => 2,
                                'payload'    => 'test-child',
                                'references' => [],
                                'parent'     => 1,
                            ],
                        ],
                    ]
                )
            )
        );

        $children = $repository->findBy(['parent' => $parent]);

        self::assertCount(1, $children);
        /** @var TestEntity $child */

        $child         = array_shift($children);

        self::assertEquals($parent, $child->getParent());
        self::assertEquals($parent->getPayload(), $child->getParent()->getPayload());
    }

    public function testSubEntityRelations()
    {
        $repository = $this->getManager()->getRepository(SubEntity::class);

        $this->getResponseMock('test-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'id'          => '1',
                            'payload'     => 'test-payload',
                            'references'  => ['5', '7'],
                            'sub-payload' => 'sub-payload',
                        ],
                    ]
                )
            )
        );

        $this->getResponseMock('test-reference-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            [
                                'id'                => '5',
                                'reference-payload' => 'test-payload-5',
                                'owner'             => '1',
                            ],
                            [
                                'id'                => '7',
                                'reference-payload' => 'test-payload-7',
                                'owner'             => '1',
                            ],
                        ],
                    ]
                )
            )
        );


        /** @var SubEntity $entity */
        $entity = $repository->find(1);
        self::assertSame(1, $entity->getId());
        self::assertSame('test-payload', $entity->getPayload());
        self::assertSame('sub-payload', $entity->getSubPayload());
        self::assertNull($entity->getStringPayload());
        /** @var TestReference[] $references */
        $references = $entity->getReferences()->toArray();

        self::assertCount(2, $references);
        foreach ($references as $reference) {
            self::assertSame('test-payload-'.$reference->getId(), $reference->getReferencePayload());
        }
    }

    protected function getClientNames()
    {
        return array_merge(parent::getClientNames(), ['test-reference-client']);
    }
}
