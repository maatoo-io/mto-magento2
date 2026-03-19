<?php

namespace Maatoo\Maatoo\Service;

use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Model\Config\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class CleanUpSyncTableService
 */
class CleanUpSyncTableService
{
    private $resource;
    private $config;
    private $storeManager;
    private $logger;

    /**
     * @param ResourceConnection $resource
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resource,
        Config $config,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param int|null $daysOverride
     *
     * @return array
     * @throws \Zend_Db_Statement_Exception
     */
    public function execute(?int $daysOverride = null): array
    {
        $summary = [
            'duplicates' => 0,
            'orphans' => 0,
            'retention' => 0,
            'products_cleaned' => 0,
            'customers_cleaned' => 0,
        ];

        try {
            $websiteId = $this->storeManager->getStore()->getWebsiteId();
        } catch (\Exception $exception) {
            $websiteId = 0;
        }

        $isEnabled = $this->config->isCleanupEnabled($websiteId);

        if (!$isEnabled && $daysOverride === null) {
            return $summary;
        }

        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('maatoo_sync');

        $duplicateSql = "DELETE ms1 FROM {$tableName} ms1
            INNER JOIN {$tableName} ms2
            WHERE ms1.sync_id > ms2.sync_id
              AND ms1.entity_type = ms2.entity_type
              AND ms1.entity_id = ms2.entity_id
              AND ms1.store_id = ms2.store_id
              AND ms1.maatoo_id = ms2.maatoo_id";

        $duplicateRows = $connection->query($duplicateSql)->rowCount();
        $summary['duplicates'] = $duplicateRows;

        if ($duplicateRows > 0) {
            $this->logger->info(sprintf('Maatoo Cleanup Step A: Removed %d duplicate records.', $duplicateRows));
        }

        $safeToDeleteIds = $this->config->getSafeToDeleteStores(0);

        $activeStoreIds = [];

        foreach ($this->storeManager->getWebsites() as $website) {
            $allowedStores = $this->config->getAllowedStores((int)$website->getId());

            if ($allowedStores) {
                $ids = explode(',', $allowedStores);

                foreach ($ids as $id) {
                    $activeStoreIds[] = (int)$id;
                }
            }
        }

        $activeStoreIds = array_unique($activeStoreIds);
        $finalDeleteList = array_diff($safeToDeleteIds, $activeStoreIds);

        if (!empty($finalDeleteList)) {
            $orphanRows = $connection->delete(
                $tableName,
                ['store_id IN (?)' => array_values($finalDeleteList)]
            );

            $summary['orphans'] = $orphanRows;

            if ($orphanRows > 0) {
                $this->logger->info(sprintf('Maatoo Cleanup Step B: Removed %d records for orphaned stores: %s', $orphanRows, implode(',', $finalDeleteList)));
            }
        }

        $days = $daysOverride ?? $this->config->getRetentionDays($websiteId);

        $retentionSql = "DELETE FROM {$tableName} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND entity_type NOT IN (?, ?)";
        $retentionRows = $connection->query($retentionSql, [
            $days, SyncInterface::TYPE_CUSTOMER, SyncInterface::TYPE_PRODUCT,
        ])->rowCount();

        $summary['retention'] = $retentionRows;

        if ($retentionRows > 0) {
            $this->logger->info(sprintf('Maatoo Cleanup Step C (General): Removed %d records older than %d days.', $retentionRows, $days));
        }

        $productEntityIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $eavAttributeTable = $this->resource->getTableName('eav_attribute');
        $productSafetySql = "DELETE ms FROM {$tableName} ms
            INNER JOIN {$productEntityIntTable} cpei ON ms.entity_id = cpei.entity_id
            INNER JOIN {$eavAttributeTable} ea ON cpei.attribute_id = ea.attribute_id AND ea.attribute_code = 'status'
            WHERE ms.entity_type = ?
            AND ms.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND cpei.value = 2
            AND cpei.store_id = 0";

        $productRows = $connection->query($productSafetySql, [SyncInterface::TYPE_PRODUCT, $days])->rowCount();
        $summary['products_cleaned'] = $productRows;

        if ($productRows > 0) {
            $this->logger->info(sprintf('Maatoo Cleanup Step D (Products): Removed %d disabled product records older than %d days.', $productRows, $days));
        }

        $customerEntityTable = $this->resource->getTableName('customer_entity');
        $customerSafetySql = "DELETE ms FROM {$tableName} ms
            INNER JOIN {$customerEntityTable} ce ON ms.entity_id = ce.entity_id
            WHERE ms.entity_type = ?
            AND ms.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND ce.is_active = 0";

        $customerRows = $connection->query($customerSafetySql, [SyncInterface::TYPE_CUSTOMER, $days])->rowCount();
        $summary['customers_cleaned'] = $customerRows;

        if ($customerRows > 0) {
            $this->logger->info(sprintf('Maatoo Cleanup Step E (Customers): Removed %d inactive customer records older than %d days.', $customerRows, $days));
        }

        return $summary;
    }
}
