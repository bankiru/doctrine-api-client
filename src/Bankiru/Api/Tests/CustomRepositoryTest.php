<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 09.02.2016
 * Time: 13:46
 */

namespace Bankiru\Api\Tests;

use Bankiru\Api\Test\Entity\CustomEntity;
use Bankiru\Api\Test\Repository\CustomTestRepository;
use GuzzleHttp\Psr7\Response;

class CustomRepositoryTest extends AbstractEntityManagerTest
{
    public function testCustomRepository()
    {
        /** @var CustomTestRepository $repository */
        $repository = $this->getManager()->getRepository(CustomEntity::class);
        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            'customField' => 'custom-response',
                        ],
                    ]
                )
            )
        );

        /** @var \StdClass $data */
        $data = $repository->doCustomStuff();

        self::assertInstanceOf(\StdClass::class, $data);
        self::assertEquals('custom-response', $data->customField);
    }
}
