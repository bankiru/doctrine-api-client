<?php
/**
 * Created by PhpStorm.
 * User: batanov.pavel
 * Date: 09.02.2016
 * Time: 13:45
 */

namespace Bankiru\Api\Test\Repository;

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
