<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\Sub\SubEntity;
use Bankiru\Api\Doctrine\Test\Entity\TestEntity;
use Bankiru\Api\Doctrine\Test\Entity\TestReference;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use ScayTrase\Api\Rpc\RpcRequestInterface;

final class ReferenceLoadingTest extends AbstractEntityManagerTest
{
    public function testAnyToOneLoading()
    {

        $repository = $this->getManager()->getRepository(TestEntity::class);

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'         => '1',
                    'payload'    => 'test-payload',
                    'references' => [],
                    'parent'     => '2',
                ]
            )
        );

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'         => '2',
                    'payload'    => 'parent-payload',
                    'references' => [],
                    'parent'     => 1,
                ]
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

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'      => '1',
                    'payload' => 'test-payload',
                ]
            )
        );

        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'                => '5',
                        'reference-payload' => 'test-payload-5',
                        'owner'             => '1',
                    ],
                    (object)[
                        'id'                => '7',
                        'reference-payload' => 'test-payload-7',
                        'owner'             => '1',
                    ],
                ]
            )
        );

        /** @var TestEntity $entity */
        $entity = $repository->find(1);

        self::assertInstanceOf(TestEntity::class, $entity);
        self::assertEquals('test-payload', $entity->getPayload());
        self::assertEquals(1, $entity->getId());
        self::assertInstanceOf(Collection::class, $entity->getReferences());
        self::assertEquals(1, $this->getClient('test-reference-client')->count());
        self::assertCount(2, $entity->getReferences());
        self::assertEquals(0, $this->getClient('test-reference-client')->count());
        foreach ($entity->getReferences() as $reference) {
        }
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

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'         => 1,
                    'payload'    => 'test-payload',
                    'references' => [],
                ]
            )
        );

        /** @var TestEntity $parent */
        $parent = $repository->find(1);
        self::assertInstanceOf(TestEntity::class, $parent);
        self::assertEquals('test-payload', $parent->getPayload());
        self::assertEquals(1, $parent->getId());

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'         => 2,
                        'payload'    => 'test-child',
                        'references' => [],
                        'parent'     => 1,
                    ],
                ]
            )
        );

        $children = $repository->findBy(['parent' => $parent]);

        self::assertCount(1, $children);
        /** @var TestEntity $child */

        $child = array_shift($children);

        self::assertEquals($parent, $child->getParent());
        self::assertEquals($parent->getPayload(), $child->getParent()->getPayload());
    }

    public function testFindByNullValue()
    {
        $repository = $this->getManager()->getRepository(TestEntity::class);
        /** @var TestEntity $parent */
        $parent = $this->getManager()->getReference(TestEntity::class, 1);

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'         => 2,
                        'payload'    => 'test-child',
                        'references' => [],
                        'parent'     => 1,
                    ],
                ]
            )
        );

        $children = $repository->findBy(['parent' => $parent]);

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'         => 3,
                        'payload'    => 'test-child-3',
                        'references' => [],
                        'parent'     => 1,
                    ],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertNull($request->getParameters()['criteria']['parent']);

                return true;
            }
        );

        $children = $repository->findBy(['parent' => null]);

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'         => 4,
                        'payload'    => 'test-child-4',
                        'references' => [],
                        'parent'     => 1,
                    ],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertNull($request->getParameters()['criteria']['payload']);

                return true;
            }
        );

        $children = $repository->findBy(['payload' => null]);
    }

    public function testSubEntityRelations()
    {
        $repository = $this->getManager()->getRepository(SubEntity::class);

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'          => '1',
                    'payload'     => 'test-payload',
                    'references'  => ['5', '7'],
                    'sub-payload' => 'sub-payload',
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('test-entity/find', $request->getMethod());
                self::assertEquals(['id' => 1], $request->getParameters());

                return true;
            }
        );

        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'                => '5',
                        'reference-payload' => 'test-payload-5',
                        'owner'             => '1',
                    ],
                    (object)[
                        'id'                => '7',
                        'reference-payload' => 'test-payload-7',
                        'owner'             => '1',
                    ],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('test-reference/search', $request->getMethod());
                self::assertEquals(
                    [
                        'criteria' => ['owner' => 1],
                        'order'    => [],
                        'limit'    => null,
                        'offset'   => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
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
            self::assertSame('test-payload-' . $reference->getId(), $reference->getReferencePayload());
        }
    }

    public function testNullValueTraitedAsNullAssociationObject()
    {
        $repository = $this->getManager()->getRepository(TestReference::class);

        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'                => '1',
                    'reference-payload' => 'test-payload-5',
                    'owner'             => null,
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('test-reference/find', $request->getMethod());
                self::assertEquals(['id' => 1], $request->getParameters());

                return true;
            }
        );

        /** @var TestReference $entity */
        $entity = $repository->find(1);

        self::assertNull($entity->getOwner());
    }

    protected function getClientNames()
    {
        return array_merge(parent::getClientNames(), ['test-reference-client']);
    }
}
