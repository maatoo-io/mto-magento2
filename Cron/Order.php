<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class Order
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\Order
     */
    private $order;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\Order $order
    )
    {
        $this->logger = $logger;
        $this->order = $order;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info("Cronjob maatoo orders is executed.");
        $this->order->sync();
    }
}

