<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\PrefixedEntity;
use ScayTrase\Api\Rpc\RpcRequestInterface;

final class CountingTest extends AbstractEntityManagerTest
{
    public function testEntityCounting()
    {
        $this->getClient()->push(
            $this->getResponseMock(true, 5),
            function (RpcRequestInterface $request) {
                self::assertEquals('prefixed-entity/count', $request->getMethod());
                self::assertEquals(
                    [
                        'criteria' => ['prefix_payload' => 'test'],
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $count = $this->getManager()->getUnitOfWork()->getEntityPersister(PrefixedEntity::class)->count(
            ['payload' => 'test']
        );

        self::assertEquals(5, $count);
    }
}
