<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\TypeEntity;
use ScayTrase\Api\Rpc\RpcRequestInterface;

class TypeTest extends AbstractEntityManagerTest
{
    const U_TIMESTAMP = 100500;
    const CUSTOM_DATETIME = '2010.10.10 00:00:00';

    public function testValuesConvertedToAPI()
    {
        $manager = $this->getManager();

        $entity    = new TypeEntity();
        $datetimeU = new \DateTime();
        $datetimeU->setTimestamp(self::U_TIMESTAMP);
        $entity->setDatetimeU($datetimeU);

        $datetimeC = \DateTime::createFromFormat('Y.m.d H:i:s', self::CUSTOM_DATETIME);
        $entity->setDatetimeC($datetimeC);

        $manager->persist($entity);

        $this->getClient()->push(
            $this->getResponseMock(true, (object)['id' => 42]),
            function(RpcRequestInterface $request) {
                self::assertEquals('test-entity/create', $request->getMethod());
                self::assertEquals(
                    [
                        'datetime_u' => self::U_TIMESTAMP,
                        'datetime_c' => self::CUSTOM_DATETIME,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $manager->flush();
    }

    public function testValuesConvertedFromAPI()
    {
        $manager = $this->getManager();

        $this->getClient()->push(
            $this->getResponseMock(
                true,
                (object)[
                    'id'       => 1,
                    'datetime_u' => self::U_TIMESTAMP,
                    'datetime_c' => self::CUSTOM_DATETIME,
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('test-entity/find', $request->getMethod());
                self::assertEquals(
                    [
                        'id' => 1,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $entity = $manager->getRepository(TypeEntity::class)->find(1);
        self::assertNotNull($entity->getDatetimeU());
        self::assertNotNull($entity->getDatetimeC());
        self::assertEquals(self::U_TIMESTAMP, $entity->getDatetimeU()->getTimestamp());
        self::assertEquals(self::CUSTOM_DATETIME, $entity->getDatetimeC()->format('Y.m.d H:i:s'));
    }
}
