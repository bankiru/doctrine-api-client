<?php

namespace Bankiru\Api\Doctrine\Test\Repository;

use Bankiru\Api\Doctrine\EntityRepository;
use Bankiru\Api\Doctrine\Rpc\RpcRequest;

class CustomTestRepository extends EntityRepository
{
    public function doCustomStuff()
    {
        $request = new RpcRequest(
            $this->getMetadata()->getMethodContainer()->getMethod('custom'),
            ['param1'=>'value1']
        );

        return $this->getClient()->invoke([$request])->getResponse($request)->getBody();
    }
}
