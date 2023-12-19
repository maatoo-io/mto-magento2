<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

use Maatoo\Maatoo\Logger\Logger;
use Maatoo\Maatoo\Service\ProductSyncService;

/**
 * Class Product
 *
 * @package Maatoo\Maatoo\Cron
 */
class Product
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ProductSyncService
     */
    private $productSyncService;

    /**
     * Product constructor.
     */
    public function __construct(
        Logger $logger,
        ProductSyncService $productSyncService
    ) {
        $this->logger = $logger;
        $this->productSyncService = $productSyncService;
    }

    /**
     * Execute the cron
     */
    public function execute(): void
    {
        $this->logger->info('Cronjob maatoo products is executed.');
        $this->productSyncService->sync();
    }
}
