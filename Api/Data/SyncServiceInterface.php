<?php

namespace Maatoo\Maatoo\Api\Data;

/**
 * Interface SyncServiceInterface
 *
 * @pakage Maatoo\Maatoo\Api\Data
 */
interface SyncServiceInterface
{
    const BATCH_SIZE_LIMIT = 100;

    public function sync(?\Closure $cl = null): void;
}
