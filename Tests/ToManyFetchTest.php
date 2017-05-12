<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\ToManyAsArrayEntity;
use ScayTrase\Api\Rpc\RpcRequestInterface;

class ToManyFetchTest extends AbstractEntityManagerTest
{
    public function testEntityManagerLoadsToManyFromArray()
    {
        $repository = $this->getManager()->getRepository(ToManyAsArrayEntity::class);

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'          => 3,
                    'payload'     => 'test-payload-3',
                    'references'  => [1, 2],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('array-entity/find', $request->getMethod());
                self::assertEquals(['id' => 3], $request->getParameters());

                return true;
            }
        );

        $this->getClient('test-client')->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'id'      => 1,
                        'payload' => 'test-payload-1',
                    ],
                    (object)[
                        'id'      => 2,
                        'payload' => 'test-payload-2',
                    ],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('array-entity/search', $request->getMethod());
                self::assertEquals(
                    [
                        'criteria' => ['id' => [1, 2]],
                        'order'    => [],
                        'limit'    => null,
                        'offset'   => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        /** @var ToManyAsArrayEntity $entity */
        $entity = $repository->find(3);
        self::assertSame(3, $entity->getId());
        self::assertSame('test-payload-3', $entity->getPayload());
        /** @var ToManyAsArrayEntity[] $references */
        $references = $entity->getChildren()->toArray();

        self::assertCount(2, $references);
        foreach ($references as $reference) {
            self::assertSame('test-payload-' . $reference->getId(), $reference->getPayload());
        }
    }
}
