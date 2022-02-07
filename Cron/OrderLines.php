<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class OrderLines
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\OrderLines
     */
    private $order;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Maatoo\Maatoo\Model\Synchronization\OrderLines $order
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
        $this->logger->info("Cronjob maatoo order lines is executed.");
        $this->order->sync();
    }
}

