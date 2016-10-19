<?php

namespace Bankiru\Api\Doctrine\Rpc;

use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use ScayTrase\Api\Rpc\RpcClientInterface;

interface CrudsApiInterface extends Creator, Finder, Patcher, Remover, Searcher, Counter
{
}
