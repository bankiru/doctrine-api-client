<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\TestEntity;

class CountingTest extends AbstractEntityManagerTest
{
    public function testEntityCounting()
    {
        $this->getClient()->push($this->getResponseMock(true, 5));

        $count = $this->getManager()->getUnitOfWork()->getEntityPersister(TestEntity::class)->count(
            ['payload' => 'test']
        );

        self::assertEquals(5, $count);
    }
}
