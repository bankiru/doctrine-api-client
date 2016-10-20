<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\TestEntity;
use Bankiru\Api\Doctrine\Test\Entity\TestReference;
use GuzzleHttp\Psr7\Response;

class CommitTest extends AbstractEntityManagerTest
{
    public function testSimpleCommit()
    {
        $entity = new TestReference();

        $this->getResponseMock('test-reference-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => 241,
                    ]
                )
            )
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

        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => 42,
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
                        'result'  => 241,
                    ]
                )
            )
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
        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => 17,
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
                        'result'  => null,
                    ]
                )
            )
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

        $this->getResponseMock('test-reference-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => 241,
                    ]
                )
            )
        );

        $this->getManager()->persist($entity);
        $this->getManager()->flush();

        self::assertNotNull($entity->getId());
        self::assertEquals(241, $entity->getId());

        $this->getResponseMock('test-reference-client')->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => null,
                    ]
                )
            )
        );

        $this->getManager()->remove($entity);
        $this->getManager()->flush();
    }


    protected function getClientNames()
    {
        return array_merge(parent::getClientNames(), ['test-reference-client']);
    }
}
