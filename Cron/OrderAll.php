<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class OrderAll
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\OrderAll
     */
    private $orderAll;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\OrderAll $orderAll
    ) {
        $this->logger = $logger;
        $this->orderAll = $orderAll;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info("Cronjob maatoo orders all is executed.");
        $this->orderAll->sync();
    }
}
