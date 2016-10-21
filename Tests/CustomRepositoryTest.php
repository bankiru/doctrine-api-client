<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\CustomEntity;
use Bankiru\Api\Doctrine\Test\Repository\CustomTestRepository;
use ScayTrase\Api\Rpc\RpcRequestInterface;

class CustomRepositoryTest extends AbstractEntityManagerTest
{
    public function testCustomRepository()
    {
        /** @var CustomTestRepository $repository */
        $repository = $this->getManager()->getRepository(CustomEntity::class);
        $this->getClient()->push(
            $this->getResponseMock(true, (object)['customField' => 'custom-response']),
            function (RpcRequestInterface $request) {
                self::assertEquals('custom-entity/custom', $request->getMethod());
                self::assertEquals(
                    ['param1' => 'value1'],
                    $request->getParameters()
                );

                return true;
            }
        );

        /** @var \StdClass $data */
        $data = $repository->doCustomStuff();

        self::assertInstanceOf(\stdClass::class, $data);
        self::assertEquals('custom-response', $data->customField);
    }
}
