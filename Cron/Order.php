<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

use Magento\Framework\Lock\LockManagerInterface;

class Order
{
    /**
     * Shared by both order cron entry points.
     */
    private const LOCK_NAME = 'maatoo_order_sync';

    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\Order
     */
    private $order;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\Order $order,
        LockManagerInterface $lockManager
    ) {
        $this->logger = $logger;
        $this->order = $order;
        $this->lockManager = $lockManager;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            $this->logger->info('Skipping maatoo order sync because another order sync is running.');
            return;
        }

        $this->logger->info('Cronjob maatoo orders is executed.');
        try {
            $this->order->sync();
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
