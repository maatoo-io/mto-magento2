<?php

namespace Maatoo\Maatoo\Cron;

use Maatoo\Maatoo\Model\Config\Config;
use Maatoo\Maatoo\Service\CleanUpSyncTableService;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class CleanUpSyncTable
 */
class CleanUpSyncTable
{
    /**
     * @var CleanUpSyncTableService
     */
    private $cleanUpService;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CleanUpSyncTableService $cleanUpService
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        CleanUpSyncTableService $cleanUpService,
        Config $config,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->cleanUpService = $cleanUpService;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isCleanupEnabled()) {
            return;
        }

        try {
            $this->cleanUpService->execute();
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Maatoo Cleanup Cron Error: %s', $e->getMessage()));
        }
    }
}
