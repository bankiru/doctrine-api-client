<?php

namespace Bankiru\Api\Doctrine\Test\Repository;

use Bankiru\Api\Doctrine\EntityRepository;
use Bankiru\Api\Doctrine\Test\RpcRequestMock;

class CustomTestRepository extends EntityRepository
{
    public function doCustomStuff()
    {
        $request = new RpcRequestMock(
            $this->getMetadata()->getMethodContainer()->getMethod('custom'),
            ['param1'=>'value1']
        );

        return $this->getClient()->invoke([$request])->getResponse($request)->getBody();
    }
}
