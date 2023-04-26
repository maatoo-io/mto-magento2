<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class Category
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\Category
     */
    private $category;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\Category $category
    )
    {
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
        $this->logger->info("Cronjob maatoo categories is executed.");
        $this->category->sync();
    }
}

