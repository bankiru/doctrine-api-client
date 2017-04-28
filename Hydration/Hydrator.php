<?php

namespace Bankiru\Api\Doctrine\Hydration;

use Bankiru\Api\Doctrine\Exception\HydrationException;

interface Hydrator
{
    /**
     * Hydrates data to new or given entity
     *
     * @param \stdClass   $source
     * @param object|null $entity
     *
     * @return object Hydrated object
     * @throws HydrationException
     */
    public function hydarate($source, $entity = null);
}
