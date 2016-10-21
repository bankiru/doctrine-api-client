<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\IndirectIdEntity;
use ScayTrase\Api\Rpc\RpcRequestInterface;

class IndirectFieldsTestAbstract extends AbstractEntityManagerTest
{
    public function testIndirectId()
    {
        $repository = $this->getManager()->getRepository(IndirectIdEntity::class);
        $this->getClient()->push(
            $this->getResponseMock(
                true,
                (object)[
                    'some-long-api-field-name' => 241,
                    'payload'                  => 'test',
                ]
            ),
            function (RpcRequestInterface $request) {
                self::assertEquals('indirect-entity/find', $request->getMethod());
                self::assertEquals(['some-long-api-field-name' => 241], $request->getParameters());

                return true;
            }
        );

        /** @var IndirectIdEntity $entity */
        $entity = $repository->find(241);

        self::assertInstanceOf(IndirectIdEntity::class, $entity);
        self::assertEquals(241, $entity->getId());
        self::assertEquals('test', $entity->getPayload());
    }
}
