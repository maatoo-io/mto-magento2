<?php

declare(strict_types=1);

namespace Maatoo\Maatoo\Setup\Patch\Schema;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Keep one synchronization row per Magento entity and store.
 */
class EnforceUniqueSyncIdentity implements SchemaPatchInterface
{
    private const TABLE = 'maatoo_sync';

    private const COLUMNS = [
        'entity_type',
        'entity_id',
        'store_id',
    ];

    /**
     * @var SchemaSetupInterface
     */
    private $setup;

    /**
     * @param SchemaSetupInterface $setup
     */
    public function __construct(SchemaSetupInterface $setup)
    {
        $this->setup = $setup;
    }

    /**
     * Deduplicate existing identities and enforce uniqueness.
     *
     * @return $this
     */
    public function apply()
    {
        $this->setup->startSetup();

        try {
            $this->removeDuplicateRows();
            $this->addUniqueIndex();
        } finally {
            $this->setup->endSetup();
        }

        return $this;
    }

    /**
     * Preserve the most actionable row for every duplicated identity.
     */
    private function removeDuplicateRows(): void
    {
        $connection = $this->setup->getConnection();
        $table = $connection->quoteIdentifier($this->setup->getTable(self::TABLE));
        $temporaryTableName = $this->setup->getTable('maatoo_sync_canonical_tmp');
        $temporaryTable = $connection->quoteIdentifier($temporaryTableName);
        $preference = $this->getPreferenceExpression('source');

        $connection->dropTemporaryTable($temporaryTableName);

        try {
            $connection->query(
                "CREATE TEMPORARY TABLE {$temporaryTable} (
                    entity_type INT NOT NULL,
                    entity_id INT NOT NULL,
                    store_id INT NOT NULL,
                    sync_id INT UNSIGNED NOT NULL,
                    PRIMARY KEY (entity_type, entity_id, store_id)
                ) ENGINE=InnoDB
                SELECT
                    source.entity_type,
                    source.entity_id,
                    source.store_id,
                    CAST(SUBSTRING_INDEX(
                        GROUP_CONCAT(source.sync_id ORDER BY {$preference} DESC, source.sync_id ASC),
                        ',',
                        1
                    ) AS UNSIGNED) AS sync_id
                FROM {$table} AS source
                GROUP BY source.entity_type, source.entity_id, source.store_id
                HAVING COUNT(*) > 1"
            );

            $connection->query(
                "DELETE duplicate FROM {$table} AS duplicate
                INNER JOIN {$temporaryTable} AS canonical
                    ON canonical.entity_type = duplicate.entity_type
                    AND canonical.entity_id = duplicate.entity_id
                    AND canonical.store_id = duplicate.store_id
                WHERE duplicate.sync_id <> canonical.sync_id"
            );
        } finally {
            $connection->dropTemporaryTable($temporaryTableName);
        }
    }

    /**
     * Rank rows with usable remote IDs, preserving pending state changes.
     *
     * @param string $alias
     */
    private function getPreferenceExpression(string $alias): string
    {
        return "CASE
            WHEN {$alias}.maatoo_id > 0 AND {$alias}.status = 3 THEN 4
            WHEN {$alias}.maatoo_id > 0 AND {$alias}.status = 2 THEN 3
            WHEN {$alias}.maatoo_id > 0 AND {$alias}.status = 1 THEN 2
            WHEN {$alias}.maatoo_id > 0 THEN 1
            ELSE 0
        END";
    }

    /**
     * Add the database-level identity guard if it is not already present.
     */
    private function addUniqueIndex(): void
    {
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable(self::TABLE);

        foreach ($connection->getIndexList($table) as $index) {
            $isIdentityIndex = $index['INDEX_TYPE'] === AdapterInterface::INDEX_TYPE_UNIQUE
                && array_values($index['COLUMNS_LIST']) === self::COLUMNS;

            if ($isIdentityIndex) {
                return;
            }
        }

        $connection->addIndex(
            $table,
            $this->setup->getIdxName(
                self::TABLE,
                self::COLUMNS,
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            self::COLUMNS,
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
