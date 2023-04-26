<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class Store
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\Store
     */
    private $store;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\Store $store
    )
    {
        $this->logger = $logger;
        $this->store = $store;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info("Cronjob maatoo stores is executed.");
        $this->store->sync();
    }
}

