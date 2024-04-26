<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

use Maatoo\Maatoo\Logger\Logger;
use Maatoo\Maatoo\Service\CategorySyncService;

class Category
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CategorySyncService
     */
    private $category;

    /**
     * Category constructor.
     */
    public function __construct(
        Logger $logger,
        CategorySyncService $category
    ) {
        $this->logger = $logger;
        $this->category = $category;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info('Cronjob maatoo categories is executed.');
        $this->category->sync();
    }
}
