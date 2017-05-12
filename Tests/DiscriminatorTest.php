<?php

namespace Bankiru\Api\Doctrine\Tests;

use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\Test\Entity\Discriminator\InheritorFirst;
use Bankiru\Api\Doctrine\Test\Entity\Discriminator\InheritorSecond;
use Bankiru\Api\Doctrine\Test\Entity\DiscriminatorBaseClass;
use ScayTrase\Api\Rpc\RpcRequestInterface;

final class DiscriminatorTest extends AbstractEntityManagerTest
{
    public function testDiscriminatorMetadataLoading()
    {
        $factory = $this->getManager()->getMetadataFactory();
        /** @var EntityMetadata $baseMetadata */
        $baseMetadata = $factory->getMetadataFor(DiscriminatorBaseClass::class);
        /** @var EntityMetadata $firstMetadata */
        $firstMetadata = $factory->getMetadataFor(InheritorFirst::class);
        /** @var EntityMetadata $secondMetadata */
        $secondMetadata = $factory->getMetadataFor(InheritorSecond::class);

        self::assertCount(3, $baseMetadata->discriminatorMap);
        self::assertEquals(strtolower('DiscriminatorBaseClass'), $baseMetadata->discriminatorValue);
        self::assertInternalType('array', $baseMetadata->discriminatorField);
        self::assertInternalType('string', $baseMetadata->discriminatorValue);

        self::assertCount(3, $firstMetadata->discriminatorMap);
        self::assertEquals(strtolower('InheritorFirst'), $firstMetadata->discriminatorValue);
        self::assertInternalType('array', $firstMetadata->discriminatorField);
        self::assertInternalType('string', $firstMetadata->discriminatorValue);

        self::assertCount(3, $secondMetadata->discriminatorMap);
        self::assertEquals(strtolower('InheritorSecond'), $secondMetadata->discriminatorValue);
        self::assertInternalType('array', $secondMetadata->discriminatorField);
        self::assertInternalType('string', $secondMetadata->discriminatorValue);
    }

    public function testDiscriminatorMapResolution()
    {
        $this->getClient()->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'type'     => strtolower('DiscriminatorBaseClass'),
                        'id_field' => 1,
                        'base'     => 1,
                        'first'    => null,
                        'second'   => null,
                    ],
                    (object)[
                        'type'     => strtolower('InheritorFirst'),
                        'id_field' => 2,
                        'base'     => 2,
                        'first'    => 2,
                        'second'   => null,
                    ],
                    (object)[
                        'type'     => strtolower('InheritorSecond'),
                        'id_field' => 3,
                        'base'     => 3,
                        'first'    => 3,
                        'second'   => 3,
                    ],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('discriminator/search', $request->getMethod());
                self::assertEquals(
                    [
                        'criteria' => [
                            'type' => [
                                strtolower('DiscriminatorBaseClass'),
                                strtolower('InheritorFirst'),
                                strtolower('InheritorSecond'),
                            ],
                        ],
                        'order'    => [],
                        'limit'    => null,
                        'offset'   => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $entities = $this->getManager()->getRepository(DiscriminatorBaseClass::class)->findAll();

        self::assertCount(3, $entities);
        /** @var DiscriminatorBaseClass $base */
        $base = array_shift($entities);
        self::assertInstanceOf(DiscriminatorBaseClass::class, $base);
        self::assertEquals(1, $base->getId());
        self::assertEquals(1, $base->getBase());

        /** @var InheritorFirst $first */
        $first = array_shift($entities);
        self::assertInstanceOf(InheritorFirst::class, $first);
        self::assertEquals(2, $first->getId());
        self::assertEquals(2, $first->getBase());
        self::assertEquals(2, $first->getFirst());

        /** @var InheritorSecond $second */
        $second = array_shift($entities);
        self::assertInstanceOf(InheritorSecond::class, $second);
        self::assertEquals(3, $second->getId());
        self::assertEquals(3, $second->getBase());
        self::assertEquals(3, $second->getFirst());
        self::assertEquals(3, $second->getSecond());
    }

    public function testSearchingDiscriminatedEntitiesAddsTypeFilter()
    {
        $this->getClient()->push(
            $this->getResponseMock(
                true,
                [
                    (object)[
                        'type'     => strtolower('InheritorFirst'),
                        'id_field' => 2,
                        'base'     => 2,
                        'first'    => 2,
                        'second'   => null,
                    ],
                    (object)[
                        'type'     => strtolower('InheritorSecond'),
                        'id_field' => 3,
                        'base'     => 3,
                        'first'    => 3,
                        'second'   => 3,
                    ],
                ]
            ),
            function(RpcRequestInterface $request) {
                self::assertEquals('discriminator/search', $request->getMethod());
                self::assertEquals(
                    [
                        'criteria' => [
                            'type' => [
                                strtolower('InheritorFirst'),
                                strtolower('InheritorSecond'),
                            ],
                        ],
                        'order'    => [],
                        'limit'    => null,
                        'offset'   => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $entities = $this->getManager()->getRepository(InheritorFirst::class)->findAll();

        /** @var InheritorFirst $first */
        $first = array_shift($entities);
        self::assertInstanceOf(InheritorFirst::class, $first);
        self::assertEquals(2, $first->getId());
        self::assertEquals(2, $first->getBase());
        self::assertEquals(2, $first->getFirst());

        /** @var InheritorSecond $second */
        $second = array_shift($entities);
        self::assertInstanceOf(InheritorSecond::class, $second);
        self::assertEquals(3, $second->getId());
        self::assertEquals(3, $second->getBase());
        self::assertEquals(3, $second->getFirst());
        self::assertEquals(3, $second->getSecond());
    }

    public function testDiscriminatorEntitiesCommit()
    {
        $this->getClient()->push(
            $this->getResponseMock(true, ['id_field' => 241]),
            function(RpcRequestInterface $request) {
                self::assertEquals('discriminator/create', $request->getMethod());
                self::assertEquals(
                    [
                        'type'  => strtolower('InheritorFirst'),
                        'base'  => null,
                        'first' => null,
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $entity = new InheritorFirst();

        $this->getManager()->persist($entity);
        $this->getManager()->flush();

        $this->getClient()->push(
            $this->getResponseMock(true, ['id_field' => 241]),
            function(RpcRequestInterface $request) {
                self::assertEquals('discriminator/patch', $request->getMethod());
                self::assertEquals(
                    [
                        'identifier' => ['id_field' => 241],
                        'patch'       => ['first' => 'test'],
                    ],
                    $request->getParameters()
                );

                return true;
            }
        );

        $entity->setFirst('test');
        $this->getManager()->flush();
    }
}
