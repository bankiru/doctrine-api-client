<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 11.04.2016
 * Time: 16:58
 */

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Exception\MappingException;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Utility\IdentifierFlattener;
use ScayTrase\Api\Rpc\RpcClientInterface;

abstract class AbstractEntityApi implements Searcher, Finder, Counter
{
    /** @var  ApiEntityManager */
    private $manager;
    /** @var  IdentifierFlattener */
    private $idFlattener;

    /**
     * AbstractEntityApi constructor.
     *
     * @param ApiEntityManager $manager
     */
    public function __construct(ApiEntityManager $manager)
    {
        $this->manager     = $manager;
        $this->idFlattener = new IdentifierFlattener($this->manager);
    }


    /** {@inheritdoc} */
    public function find(RpcClientInterface $client, ApiMetadata $metadata, array $identifiers)
    {
        $request = new RpcRequest($metadata->getMethodContainer()->getMethod('find'), $identifiers);

        $entityCache = $this->manager->getEntityCache();
        if (null !== $entityCache) {
            $body = $entityCache->get($metadata->getName(), $identifiers);

            if (null !== $body) {
                return $body;
            }
        }
        $response = $client->invoke([$request])->getResponse($request);

        if (!$response->isSuccessful()) {
            return null;
        }

        $body = $response->getBody();
        if (null !== $entityCache) {
            $entityCache->set($body, $metadata, $identifiers);
        }

        return $body;
    }

    /**
     * Returns key for entity
     *
     * @param ApiMetadata $metadata
     * @param array       $identifiers
     *
     * @return mixed
     */
    protected function getEntityKey(ApiMetadata $metadata, array $identifiers)
    {
        $flattenIdentifiers = $this->idFlattener->flattenIdentifier($metadata, $identifiers);

        return sprintf('%s %s', $metadata->getName(), implode(' ', $flattenIdentifiers));
    }

    /**
     * @return ApiEntityManager
     */
    protected function getManager()
    {
        return $this->manager;
    }

    /**
     * @param array|mixed $id
     *
     * @return array
     * @throws MappingException
     */
    protected function fixScalarId($id, ApiMetadata $metadata)
    {
        if (is_array($id)) {
            return $id;
        }

        $id = (array)$id;

        $identifiers = $metadata->getIdentifierFieldNames();
        if (count($id) !== count($identifiers)) {
            throw MappingException::invalidIdentifierStructure();
        }

        return array_combine($identifiers, (array)$id);
    }

}
