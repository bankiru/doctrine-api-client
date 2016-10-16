<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

final class CompositeApi implements CrudsApiInterface
{
    /** @var Counter */
    private $counter;
    /** @var Searcher */
    private $searcher;
    /** @var Finder */
    private $finder;
    /** @var Patcher */
    private $patcher;
    /** @var Remover */
    private $remover;
    /** @var Creator */
    private $creator;

    /**
     * CompositeApi constructor.
     *
     * @param Finder   $finder
     * @param Searcher $searcher
     * @param Creator  $creator
     * @param Patcher  $patcher
     * @param Counter  $counter
     * @param Remover  $remover
     */
    public function __construct(
        Finder $finder,
        Searcher $searcher,
        Creator $creator,
        Patcher $patcher,
        Counter $counter,
        Remover $remover
    ) {
        $this->finder   = $finder;
        $this->searcher = $searcher;
        $this->creator  = $creator;
        $this->patcher  = $patcher;
        $this->remover  = $remover;
        $this->counter  = $counter;
    }


    /** {@inheritdoc} */
    public function count(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        return $this->counter->count($client, $metadata, $parameters);
    }

    /** {@inheritdoc} */
    public function create(RpcClientInterface $client, ApiMetadata $metadata, array $data)
    {
        return $this->creator->create($client, $metadata, $data);
    }

    /** {@inheritdoc} */
    public function find(RpcClientInterface $client, ApiMetadata $metadata, array $identifier)
    {
        return $this->finder->find($client, $metadata, $identifier);
    }

    /** {@inheritdoc} */
    public function patch(RpcClientInterface $client, ApiMetadata $metadata, array $data, array $fields)
    {
        return $this->patcher->patch($client, $metadata, $data, $fields);
    }

    /** {@inheritdoc} */
    public function remove(RpcClientInterface $client, ApiMetadata $metadata, array $identifier)
    {
        return $this->remover->remove($client, $metadata, $identifier);
    }

    /** {@inheritdoc} */
    public function search(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        return $this->searcher->search($client, $metadata, $parameters);
    }
}
