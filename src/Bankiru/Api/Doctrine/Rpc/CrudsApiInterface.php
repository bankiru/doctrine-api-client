<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface CrudsApiInterface extends Creator, Finder, Patcher, Remover, Searcher, Counter
{
}
