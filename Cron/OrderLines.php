<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

use Maatoo\Maatoo\Plugin\ValidateCartCheckout;

class OrderLines
{
    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\OrderLines
     */
    private $order;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
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
        ValidateCartCheckout::$checker = true;
        $this->logger->info("Cronjob maatoo order lines is executed.");
        $this->order->sync();
    }
}

