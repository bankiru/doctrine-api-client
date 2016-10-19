<?php

namespace Bankiru\Api\Doctrine\Rpc;

interface Patcher extends EntityApiInterface
{
    /**
     * Performs update of the entity. If API does not support PATCH-like request - just ignore fields argument
     *
     * @param array    $identifier
     * @param array    $data   Instance of metadata-described entity
     * @param string[] $fields List of modified fields
     *
     * @return void
     */
    public function patch(array $identifier, array $data, array $fields);
}
