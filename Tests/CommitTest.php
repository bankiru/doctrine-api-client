<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\TestEntity;
use Bankiru\Api\Doctrine\Test\Entity\TestReference;
use ScayTrase\Api\Rpc\RpcRequestInterface;

class CommitTest extends AbstractEntityManagerTest
{
    public function testSimpleCommit()
    {
        $entity = new TestReference();

        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(true, 241),
            function (RpcRequestInterface $request) {
                self::assertEquals('test-reference/create', $request->getMethod());
                self::assertEquals(
                    [
                        'reference-payload' => '',
                        'owner'             => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $this->getManager()->persist($entity);
        $this->getManager()->flush();

        self::assertNotNull($entity->getId());
        self::assertEquals(241, $entity->getId());
    }

    public function testExtendedIdIsSameAsSimpleCommit()
    {
        $entity = new TestReference();

        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(true, ['id' => 241]),
            function (RpcRequestInterface $request) {
                self::assertEquals('test-reference/create', $request->getMethod());
                self::assertEquals(
                    [
                        'reference-payload' => '',
                        'owner'             => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $this->getManager()->persist($entity);
        $this->getManager()->flush();

        self::assertNotNull($entity->getId());
        self::assertEquals(241, $entity->getId());
    }

    public function testChainCommitWithRelation()
    {
        $entity = new TestReference();
        $parent = new TestEntity();
        $entity->setOwner($parent);

        $this->getClient()->push(
            $this->getResponseMock(true, ['id' => 42]),
            function (RpcRequestInterface $request) {
                self::assertEquals('test-entity/create', $request->getMethod());
                self::assertEquals(
                    [
                        'payload' => '',
                        'parent'  => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(true, ['id' => 241]),
            function (RpcRequestInterface $request) {
                self::assertEquals('test-reference/create', $request->getMethod());
                self::assertEquals(
                    [
                        'reference-payload' => '',
                        'owner'             => 42,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $this->getManager()->persist($entity);
        $this->getManager()->persist($parent);
        $this->getManager()->flush();

        self::assertNotNull($parent->getId());
        self::assertEquals(42, $parent->getId());

        self::assertNotNull($entity->getId());
        self::assertEquals(241, $entity->getId());

        self::assertEquals($entity->getOwner(), $parent);

        return $entity;
    }

    public function testChainUpdateWithRelation()
    {
        $entity = $this->testChainCommitWithRelation();

        $oldParent = $entity->getOwner();
        $newParent = new TestEntity();
        $this->getClient()->push(
            $this->getResponseMock(true, ['id' => 17]),
            function (RpcRequestInterface $request) {
                self::assertEquals('test-entity/create', $request->getMethod());
                self::assertEquals(
                    [
                        'payload' => '',
                        'parent'  => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );
        $this->getClient('test-reference-client')->push(
            $this->getResponseMock(true, null),
            function (RpcRequestInterface $request) {
                self::assertEquals('test-reference/patch', $request->getMethod());
                self::assertEquals(
                    [
                        'identifier' => ['id' => 241],
                        'patch'      => ['owner' => 17],
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $entity->setOwner($newParent);

        $this->getManager()->persist($newParent);
        $this->getManager()->flush();

        self::assertNotNull($newParent->getId());
        self::assertEquals(17, $newParent->getId());

        self::assertNotNull($entity->getId());
        self::assertEquals(241, $entity->getId());

        self::assertEquals($entity->getOwner(), $newParent);

        self::assertNotSame($newParent, $oldParent);
        self::assertFalse($oldParent->getReferences()->contains($entity));
    }

    public function testRemove()
    {
        $entity = new TestReference();

        $this->getClient('test-reference-client')->push($this->getResponseMock(true, 241));

        $this->getManager()->persist($entity);
        $this->getManager()->flush();

        self::assertNotNull($entity->getId());
        self::assertEquals(241, $entity->getId());

        $this->getClient('test-reference-client')->push($this->getResponseMock(true, null));

        $this->getManager()->remove($entity);
        $this->getManager()->flush();
    }

    protected function getClientNames()
    {
        return array_merge(parent::getClientNames(), ['test-reference-client']);
    }
}
