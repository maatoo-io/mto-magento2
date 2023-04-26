<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class OrderLinesAll
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\OrderLinesAll
     */
    private $orderLinesAll;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\OrderLinesAll $orderLinesAll
    ) {
        $this->logger = $logger;
        $this->orderLinesAll = $orderLinesAll;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info("Cronjob maatoo order lines is executed.");
        $this->orderLinesAll->sync();
    }
}
