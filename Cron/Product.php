<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Cron;

class Product
{

    protected $logger;
    /**
     * @var \Maatoo\Maatoo\Model\Synchronization\Product
     */
    private $product;

    public function __construct(
        \Maatoo\Maatoo\Logger\Logger $logger,
        \Maatoo\Maatoo\Model\Synchronization\Product $product
    )
    {
        $this->logger = $logger;
        $this->product = $product;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info("Cronjob maatoo products is executed.");
        $this->product->sync();
    }
}

