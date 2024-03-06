<?php

namespace Maatoo\Maatoo\Service;

use Maatoo\Maatoo\Adapter\AdapterInterface;
use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Api\Data\SyncServiceInterface;
use Maatoo\Maatoo\Logger\Logger;
use Maatoo\Maatoo\Model\StoreConfigManager;
use Maatoo\Maatoo\Model\StoreMap;
use Maatoo\Maatoo\Model\SyncRepository;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Helper\Category as MagentoCategoryHelper;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Class CategorySyncService
 *
 * @package Maatoo\Maatoo\Service
 */
class CategorySyncService
{
    const SUCCSESS_RESPONCE_MESSAGES_FORMATS = [
        'product-categories/batch/new' => 'Added category #%s %s to maatoo',
        'product-categories/batch/edit' => 'Updated category #%s %s in maatoo',
    ];

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var StoreConfigManager
     */
    private $storeManager;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var MagentoCategoryHelper
     */
    private $categoryHelper;

    /**
     * @var StoreMap
     */
    private $storeMap;

    /**
     * @var SyncRepository
     */
    private $syncRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * CategorySyncService constructor.
     *
     * @param StoreConfigManager $storeManager
     * @param CollectionFactory $collectionFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param MagentoCategoryHelper $categoryHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param AdapterInterface $adapter
     * @param StoreMap $storeMap
     * @param SyncRepository $syncRepository
     * @param Logger $logger
     */
    public function __construct(
        StoreConfigManager          $storeManager,
        CollectionFactory           $collectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        MagentoCategoryHelper       $categoryHelper,
        SearchCriteriaBuilder       $searchCriteriaBuilder,
        AdapterInterface            $adapter,
        StoreMap                    $storeMap,
        SyncRepository              $syncRepository,
        Logger                      $logger
    ) {
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->categoryHelper = $categoryHelper;
        $this->adapter = $adapter;
        $this->storeMap = $storeMap;
        $this->syncRepository = $syncRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Syncs categories to the maatoo platform, logging the process and checking if stores are loaded before syncing.
     */
    public function sync(?\Closure $cl = null)
    {
        $this->logger->info('Begin syncing categories to maatoo.');
        $parameters = [];
        $storesMaatoo = $this->adapter->makeRequest('stores', $parameters);

        if (empty($storesMaatoo['total'])) {
            $this->logSyncResponseData('Before loading categories you must load stores', $cl);

            return;
        }

        foreach ($this->storeManager->getStores() as $store) {
            $this->syncCategoriesByStore($store, $cl);
        }

        $this->logger->info('Finished syncing categories to maatoo.');
    }

    /**
     * Syncs categories between a store and a maatoo platform, adding or updating categories based on their sync status.
     */
    private function syncCategoriesByStore(StoreInterface $store, ?\Closure $cl): void
    {
        $this->logSyncResponseData(
            sprintf(
                'Start category synchronisation process for shop %s ',
                $store->getName()
            ),
            $cl
        );

        $storeId = $store->getId();

        if (empty($this->storeMap->getStoreToMaatoo($storeId))) {
            $this->logSyncResponseData(sprintf('store #%s not synced to maatoo yet.', $storeId), $cl);

            return;
        }

        $categoriesDataToCreate = [];
        $categoriesDataToUpdate = [];

        $collection = $this->collectionFactory->create();
        $collection
            ->getSelect()
            ->joinLeft(
                ['sync' => $collection->getTable('maatoo_sync')],
                sprintf(
                    "(e.entity_id = sync.entity_id) AND (sync.entity_type=\"%s\") AND (sync.store_id=\"%s\")",
                    SyncInterface::TYPE_CATEGORY,
                    $storeId
                ),
                [
                    'sync_entity_type' => 'sync.entity_type',
                    'sync_status'      => 'sync.status',
                    'sync_maatoo_id'   => 'sync.maatoo_id',
                    'store_id'         => 'sync.store_id',
                ]
            );

        foreach ($collection as $item) {
            /** @var CategoryInterface $category */
            $category = $this->categoryRepository->get($item->getId(), $storeId);

            if ($category->getStoreId() != $storeId) {
                continue;
            }

            if (!empty($category->getId()) && !empty($category->getUrlKey())) {
                $parameters = [
                    'store'              => $this->storeMap->getStoreToMaatoo($storeId),
                    'name'               => $category->getName(),
                    'alias'              => $category->getUrlKey(),
                    'url'                => $this->categoryHelper->getCategoryUrl($category),
                    'externalCategoryId' => $category->getId(),
                ];

                if (empty($item->getSyncStatus()) ||
                    $item->getSyncStatus() == SyncInterface::STATUS_EMPTY
                ) {
                    $categoriesDataToCreate[$category->getId()] = $parameters;
                } elseif ($item->getSyncStatus() == SyncInterface::STATUS_UPDATED) {
                    $parameters['id'] = $item->getData('sync_maatoo_id');
                    $categoriesDataToUpdate[$category->getId()] = $parameters;
                }
            }
        }

        // Create categories via batch
        $this->executesCreateCategoriesRequest($categoriesDataToCreate, $storeId, $cl);

        // Update categories request
        $this->executesUpdateCategoriesRequests($categoriesDataToUpdate, $storeId, $cl);

        // Delete entity
        $this->executesDeleteCategoriesRequests($storeId, $cl);

        $this->logSyncResponseData('Category synchronisation process completed', $cl);
    }

    /**
     * Executes a request to create a new category
     */
    private function executesCreateCategoriesRequest(
        array     $categoriesDataToCreate,
        int       $storeId,
        ?\Closure $cl = null
    ): void {
        $categoriesDataToCreateList = array_chunk(
            $categoriesDataToCreate,
            SyncServiceInterface::BATCH_SIZE_LIMIT,
            true
        );

        foreach ($categoriesDataToCreateList as $categoriesList) {
            $this->processProductsRequest(
                'product-categories/batch/new',
                'POST',
                $categoriesList,
                $storeId,
                $cl
            );
        }
    }

    /**
     * Executes update requests for categories and sync data, logging the updates and calling a closure if provided.
     */
    private function executesUpdateCategoriesRequests(
        array     $categoriesDataToUpdate,
        int       $storeId,
        ?\Closure $cl = null
    ): void {
        $categoriesDataToUpdateList = array_chunk($categoriesDataToUpdate, SyncServiceInterface::BATCH_SIZE_LIMIT, true);

        foreach ($categoriesDataToUpdateList as $categoriesList) {
            $this->processProductsRequest(
                'product-categories/batch/edit',
                'PATCH',
                $categoriesList,
                $storeId,
                $cl
            );
        }
    }

    /**
     * Processes a request for categories, updates the sync data for each category, and logs any errors that occur.
     */
    private function processProductsRequest(
        string    $endpoint,
        string    $method,
        array     $categoriesListData,
        int       $storeId,
        ?\Closure $cl = null
    ): void {
        $result = $this->adapter->makeRequest(
            $endpoint,
            $categoriesListData,
            $method
        ) ?? null;

        if (isset($result['categories'])) {
            foreach ($result['categories'] as $categoryData) {
                $syncData = $categoriesListData[$categoryData['externalCategoryId']] ?? [];

                if (!$syncData) {
                    continue;
                }

                $this->updateMaatooSyncTableData($categoryData, $categoryData['externalCategoryId'], $storeId);

                $this->logSyncResponseData(
                    sprintf(
                        self::SUCCSESS_RESPONCE_MESSAGES_FORMATS[$endpoint],
                        $categoryData['externalCategoryId'] ?? '',
                        $categoryData['name'] ?? ''
                    ),
                    $cl
                );
            }
        }

        if (isset($result['errors'])) {
            foreach ($result['errors'] as $entityId => $errorData) {
                $syncData = $categoriesListData[$entityId] ?? [];

                if (!$syncData) {
                    continue;
                }

                $this->logSyncResponseData(
                    sprintf(
                        'An error occurred while sending the category #%s %s data in maatoo: %s',
                        $entityId,
                        $categoryData['name'] ?? '',
                        $errorData['message'] ?? ''
                    ),
                    $cl
                );
            }
        }
    }

    /**
     * The function updates the Maatoo sync table data with the provided result, item ID, and store ID.
     */
    private function updateMaatooSyncTableData(array $result, int $itemId, int $storeId): void
    {
        $param = [
            'entity_id'   => $itemId,
            'entity_type' => SyncInterface::TYPE_CATEGORY,
            'store_id'    => $storeId,
        ];

        $sync = $this->syncRepository->getByParam($param);
        $sync->setStatus(SyncInterface::STATUS_SYNCHRONIZED);
        $sync->setMaatooId($result['id']);
        $sync->setEntityId($itemId);
        $sync->setStoreId($storeId);
        $sync->setEntityType(SyncInterface::TYPE_CATEGORY);
        $this->syncRepository->save($sync);
    }

    /**
     * The function executes delete requests for categories with a specific store ID and logs the response data.
     */
    private function executesDeleteCategoriesRequests(int $storeId, ?\Closure $cl = null): void
    {
        $this->searchCriteriaBuilder->addFilter('entity_type', SyncInterface::TYPE_CATEGORY);
        $this->searchCriteriaBuilder->addFilter('status', SyncInterface::STATUS_DELETED);
        $this->searchCriteriaBuilder->addFilter('store_id', $storeId);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $collectionForDelete = $this->syncRepository->getList($searchCriteria);

        foreach ($collectionForDelete as $item) {
            $this->adapter->makeRequest(
                sprintf('product-categories/%s/delete', $item->getMaatooId()), [], 'DELETE'
            );

            $this->logSyncResponseData(
                sprintf('Deleted category from maatoo with id #%s', $item->getMaatooId()),
                $cl
            );

            $this->syncRepository->delete($item);
        }
    }

    /**
     * Logs a message using the logger and optionally executes a closure function with the message as an argument.
     */
    private function logSyncResponseData(string $format, ?\Closure $cl = null): void
    {
        $this->logger->info($format);

        if (is_callable($cl)) {
            $cl($format);
        }
    }
}
