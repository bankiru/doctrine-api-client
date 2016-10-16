<?php

namespace Bankiru\Api\Tests;

use Bankiru\Api\Test\Entity\TestEntity;
use GuzzleHttp\Psr7\Response;

class CountingTest extends AbstractEntityManagerTest
{
    public function testEntityCounting()
    {
        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => 5,
                    ]
                )
            )
        );

        $count = $this->getManager()->getUnitOfWork()->getEntityPersister(TestEntity::class)->count(
            ['payload' => 'test']
        );

        self::assertEquals(5, $count);
    }
}
