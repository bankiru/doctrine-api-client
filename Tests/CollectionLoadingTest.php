<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\EntityRepository;
use Bankiru\Api\Doctrine\Proxy\ApiCollection;
use Bankiru\Api\Doctrine\Test\Entity\Sub\SubEntity;
use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Psr7\Response;

class CollectionLoadingTest extends AbstractEntityManagerTest
{
    public function testLazyCollections()
    {
        /** @var EntityRepository $repository */
        $repository = $this->getManager()->getRepository(SubEntity::class);
        /** @var ApiCollection $collection */
        $collection = $repository->createLazyCollection(['subPayload' => 'sub-payload']);

        self::assertInstanceOf(ApiCollection::class, $collection);
        self::assertFalse($collection->isInitialized());

        try {
            $collection->count();
            self::fail('Should fail');
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
        self::assertInternalType('array', $entities);
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

    protected function getClientNames()
    {
        return [self::DEFAULT_CLIENT, 'test-reference-client'];
    }
}
