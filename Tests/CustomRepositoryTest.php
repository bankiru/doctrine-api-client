<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Test\Entity\CustomEntity;
use Bankiru\Api\Doctrine\Test\Repository\CustomTestRepository;

class CustomRepositoryTest extends AbstractEntityManagerTest
{
    public function testCustomRepository()
    {
        /** @var CustomTestRepository $repository */
        $repository = $this->getManager()->getRepository(CustomEntity::class);
        $this->getClient()->push($this->getResponseMock(true, (object)['customField' => 'custom-response']));

        /** @var \StdClass $data */
        $data = $repository->doCustomStuff();

        self::assertInstanceOf(\stdClass::class, $data);
        self::assertEquals('custom-response', $data->customField);
    }
}
