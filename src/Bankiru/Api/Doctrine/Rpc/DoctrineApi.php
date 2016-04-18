<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 29.01.2016
 * Time: 11:56
 */

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

/** @internal */
final class DoctrineApi extends AbstractEntityApi
{
    /** @var  array */
    private $critera;
    /** @var  array|null */
    private $order;
    /** @var  int|null */
    private $limit;
    /** @var  int|null */
    private $offset;

    /** {@inheritdoc} */
    public function search(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        call_user_func_array([$this, 'configure'], $parameters);
        $parameters = $this->getSearchRequestParams();
        $request    = new RpcRequest($metadata->getMethodContainer()->getMethod('search'), $parameters);

        return $client->invoke($request)->getResponse($request)->getBody();
    }

    /**
     * @return array
     */
    private function getSearchRequestParams()
    {
        $filter = $this->critera;
        $sort   = $this->order;
        $offset = (int)$this->offset;
        $limit  = $this->limit ? (int)$this->limit : null;

        return [
            'criteria' => $filter,
            'sort'     => $sort,
            'limit'    => $limit,
            'offset'   => $offset,
        ];
    }

    /** {@inheritdoc} */
    public function count(RpcClientInterface $client, ApiMetadata $metadata, array $parameters)
    {
        call_user_func_array([$this, 'configure'], $parameters);
        $parameters = $this->getSearchRequestParams();
        $request    = new RpcRequest($metadata->getMethodContainer()->getMethod('count'), $parameters);

        return (int)$client->invoke($request)->getResponse($request)->getBody();
    }

    private function configure(array $criteria, array $order = null, $limit = null, $offset = null)
    {
        $this->critera = $criteria;
        $this->order   = $order;
        $this->limit   = $limit;
        $this->offset  = $offset;
    }
}
