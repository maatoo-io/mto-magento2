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
    const BATCH_SIZE = 1000;

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

        // ==========================================
        // Step A: Duplicates Cleanup
        // ==========================================
        $stepStart = microtime(true);

        $selectDuplicateIdsSql = "SELECT ms1.sync_id FROM {$tableName} ms1
        INNER JOIN {$tableName} ms2 ON ms1.entity_type = ms2.entity_type
          AND ms1.entity_id = ms2.entity_id
          AND ms1.store_id = ms2.store_id
          AND ms1.maatoo_id = ms2.maatoo_id
        WHERE ms1.sync_id > ms2.sync_id
        LIMIT " . self::BATCH_SIZE;

        do {
            $duplicateIds = array_map('intval', $connection->fetchCol($selectDuplicateIdsSql));

            if (!empty($duplicateIds)) {
                $affectedRows = $connection->delete($tableName, ['sync_id IN (?)' => $duplicateIds]);
                $summary['duplicates'] += $affectedRows;
                usleep(10000);
            } else {
                $affectedRows = 0;
            }
        } while ($affectedRows > 0);

        $summary['duplicates_time'] = round(microtime(true) - $stepStart, 4);
        $this->logger->info(sprintf(
            'Maatoo Cleanup Step A (Duplicates): Removed %d records. Execution time: %f sec.',
            $summary['duplicates'],
            $summary['duplicates_time']
        ));

        // ==========================================
        // Step B: Orphans Cleanup
        // ==========================================
        $stepStart = microtime(true);
        $safeToDeleteIds = array_map('intval', $this->config->getSafeToDeleteStores(0));

        if (!empty($safeToDeleteIds)) {
            $distinctStoreIdsSql = "SELECT DISTINCT store_id FROM {$tableName}";
            $existingStoreIds = array_map('intval', $connection->fetchCol($distinctStoreIdsSql));
            $storesToVerify = array_intersect($safeToDeleteIds, $existingStoreIds);

            if (!empty($storesToVerify)) {
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
                $finalDeleteList = array_values(array_diff($storesToVerify, $activeStoreIds));

                if (!empty($finalDeleteList)) {
                    // Using quoteInto to safely build the IN clause for the array
                    $orphanWhereIn = $connection->quoteInto('store_id IN (?)', $finalDeleteList);
                    $selectOrphanIdsSql = "SELECT sync_id FROM {$tableName}
                        WHERE {$orphanWhereIn}
                        LIMIT " . self::BATCH_SIZE;

                    do {
                        $orphanIds = array_map('intval', $connection->fetchCol($selectOrphanIdsSql));

                        if (!empty($orphanIds)) {
                            $affectedRows = $connection->delete($tableName, ['sync_id IN (?)' => $orphanIds]);
                            $summary['orphans'] += $affectedRows;
                            usleep(10000);
                        } else {
                            $affectedRows = 0;
                        }
                    } while ($affectedRows > 0);
                }
            }
        }

        $summary['orphans_time'] = round(microtime(true) - $stepStart, 4);
        $this->logger->info(sprintf(
            'Maatoo Cleanup Step B (Orphans): Removed %d records. Execution time: %f sec.',
            $summary['orphans'],
            $summary['orphans_time']
        ));

        $days = $daysOverride ?? $this->config->getRetentionDays($websiteId);

        // ==========================================
        // Step C: General Retention
        // ==========================================
        $stepStart = microtime(true);

        $selectRetentionIdsSql = "SELECT sync_id FROM {$tableName}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND entity_type NOT IN (?, ?)
            LIMIT " . self::BATCH_SIZE;

        do {
            $retentionIds = array_map('intval', $connection->fetchCol($selectRetentionIdsSql, [
                $days, SyncInterface::TYPE_CUSTOMER, SyncInterface::TYPE_PRODUCT,
            ]));

            if (!empty($retentionIds)) {
                $affectedRows = $connection->delete($tableName, ['sync_id IN (?)' => $retentionIds]);
                $summary['retention'] += $affectedRows;
                usleep(10000);
            } else {
                $affectedRows = 0;
            }
        } while ($affectedRows > 0);

        $summary['retention_time'] = round(microtime(true) - $stepStart, 4);
        $this->logger->info(sprintf(
            'Maatoo Cleanup Step C (General Retention): Removed %d records older than %d days. Execution time: %f sec.',
            $summary['retention'],
            $days,
            $summary['retention_time']
        ));

        // ==========================================
        // Step D: Product Safety Cleanup
        // ==========================================
        $stepStart = microtime(true);
        $productEntityIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $eavAttributeTable = $this->resource->getTableName('eav_attribute');

        $selectProductIdsSql = "SELECT ms.sync_id FROM {$tableName} ms
        INNER JOIN {$productEntityIntTable} cpei ON ms.entity_id = cpei.entity_id
        INNER JOIN {$eavAttributeTable} ea ON cpei.attribute_id = ea.attribute_id AND ea.attribute_code = 'status'
        WHERE ms.entity_type = ?
          AND ms.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND cpei.value = 2
          AND cpei.store_id = 0
        LIMIT " . self::BATCH_SIZE;

        do {
            $productIds = array_map('intval', $connection->fetchCol($selectProductIdsSql, [SyncInterface::TYPE_PRODUCT, $days]));

            if (!empty($productIds)) {
                $affectedRows = $connection->delete($tableName, ['sync_id IN (?)' => $productIds]);
                $summary['products_cleaned'] += $affectedRows;
                usleep(10000);
            } else {
                $affectedRows = 0;
            }
        } while ($affectedRows > 0);

        $summary['products_time'] = round(microtime(true) - $stepStart, 4);
        $this->logger->info(sprintf(
            'Maatoo Cleanup Step D (Products): Removed %d disabled product records older than %d days. Execution time: %f sec.',
            $summary['products_cleaned'],
            $days,
            $summary['products_time']
        ));

        // ==========================================
        // Step E: Customer Safety Cleanup
        // ==========================================
        $stepStart = microtime(true);
        $customerEntityTable = $this->resource->getTableName('customer_entity');

        $selectCustomerIdsSql = "SELECT ms.sync_id FROM {$tableName} ms
        INNER JOIN {$customerEntityTable} ce ON ms.entity_id = ce.entity_id
        WHERE ms.entity_type = ?
          AND ms.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND ce.is_active = 0
        LIMIT " . self::BATCH_SIZE;

        do {
            $customerIds = array_map('intval', $connection->fetchCol($selectCustomerIdsSql, [SyncInterface::TYPE_CUSTOMER, $days]));

            if (!empty($customerIds)) {
                $affectedRows = $connection->delete($tableName, ['sync_id IN (?)' => $customerIds]);
                $summary['customers_cleaned'] += $affectedRows;
                usleep(10000);
            } else {
                $affectedRows = 0;
            }
        } while ($affectedRows > 0);

        $summary['customers_time'] = round(microtime(true) - $stepStart, 4);
        $this->logger->info(sprintf(
            'Maatoo Cleanup Step E (Customers): Removed %d inactive customer records older than %d days. Execution time: %f sec.',
            $summary['customers_cleaned'],
            $days,
            $summary['customers_time']
        ));

        return $summary;
    }
}
