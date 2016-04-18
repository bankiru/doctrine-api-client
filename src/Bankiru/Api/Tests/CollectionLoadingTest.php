<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 03.02.2016
 * Time: 15:03
 */

namespace Bankiru\Api\Tests;

use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Test\Entity\Sub\SubEntity;
use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Psr7\Response;

class CollectionLoadingTest extends AbstractEntityManagerTest
{
    public function testLazyCollections()
    {
        $repository = $this->getManager()->getRepository(SubEntity::class);
        /** @var SubEntity[]|ArrayCollection|ApiCollection $entities */
        $entities = $repository->findBy(['subPayload' => 'sub-payload']);

        self::assertInstanceOf(ApiCollection::class, $entities);
        self::assertFalse($entities->isInitialized());

        try {
            $entities->count();
        } catch (\OutOfBoundsException $exception) {
            self::assertEquals('Mock queue is empty', $exception->getMessage());
        }
    }

    public function testFindBy()
    {
        $repository = $this->getManager()->getRepository(SubEntity::class);

        $this->getResponseMock()->append(
            new Response(
                200,
                [],
                json_encode(
                    [
                        'jsonrpc' => '2.0',
                        'id'      => 'test',
                        'result'  => [
                            [
                                'id'             => '1',
                                'payload'        => 'test-payload-1',
                                'sub-payload'    => 'sub-payload',
                                'string-payload' => null,
                            ],
                            [
                                'id'             => '2',
                                'payload'        => 'test-payload-2',
                                'sub-payload'    => 'sub-payload',
                                'string-payload' => 123456,
                            ],
                            [
                                'id'             => '3',
                                'payload'        => 'test-payload-3',
                                'sub-payload'    => 'sub-payload',
                                'string-payload' => 'sub-payload',
                            ],
                        ],
                    ]
                )
            )
        );

        /** @var SubEntity[]|ArrayCollection $entities */
        $entities = $repository->findBy(['subPayload' => 'sub-payload']);
        self::assertInstanceOf(\Countable::class, $entities);
        self::assertCount(3, $entities);

        foreach ($entities as $entity) {
            self::assertInternalType('int', $entity->getId());
            self::assertInstanceOf(SubEntity::class, $entity);
            self::assertEquals('test-payload-' . $entity->getId(), $entity->getPayload());
            self::assertEquals('sub-payload', $entity->getSubPayload());

            if (null !== $entity->getStringPayload()) {
                self::assertInternalType('string', $entity->getStringPayload());
            }
        }
    }
}
