<?php

namespace Maatoo\Maatoo\Service;

use Maatoo\Maatoo\Adapter\AdapterInterface;
use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Api\Data\SyncServiceInterface;
use Maatoo\Maatoo\Helper\Synchronisation\ProductSyncHelper;
use Maatoo\Maatoo\Logger\Logger;
use Maatoo\Maatoo\Model\StoreConfigManager;
use Maatoo\Maatoo\Model\StoreMap;
use Maatoo\Maatoo\Model\Sync;
use Maatoo\Maatoo\Model\Synchronization\Category;
use Maatoo\Maatoo\Model\SyncRepository;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Class ProductSyncService
 *
 * @package Maatoo\Maatoo\Service
 */
class ProductSyncService implements SyncServiceInterface
{
    private Logger $logger;
    private Category $syncCategory;
    private AdapterInterface $adapter;
    private StoreConfigManager $storeManager;
    private StoreMap $storeMap;
    private CollectionFactory $collectionFactory;
    private SyncRepository $syncRepository;
    private ProductRepositoryInterface $productRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private ProductSyncHelper $productSyncHelper;
    private StockRegistryInterface $stockRegistry;

    /**
     * ProductSyncService constructor.
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CollectionFactory          $collectionFactory,
        SearchCriteriaBuilder      $searchCriteriaBuilder,
        StoreConfigManager         $storeManager,
        AdapterInterface           $adapter,
        StoreMap                   $storeMap,
        SyncRepository             $syncRepository,
        Category                   $syncCategory,
        StockRegistryInterface     $stockRegistry,
        Logger                     $logger,
        ProductSyncHelper          $productSyncHelper
    ) {
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->adapter = $adapter;
        $this->storeMap = $storeMap;
        $this->syncRepository = $syncRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->syncCategory = $syncCategory;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
        $this->productSyncHelper = $productSyncHelper;
    }

    /**
     * Responsible for synchronizing products from a Magento store to the Maatoo platform.
     */
    public function sync(?\Closure $cl = null): void
    {
        $this->logger->info('Begin syncing products to maatoo.');
        $this->syncCategory->sync($cl);

        $productsDataToUpdate = [];
        $syncDataToUpdate = [];
        $parameters = [];
        $categoryMaatoo = $this->adapter->makeRequest('product-categories', $parameters);

        if (empty($categoryMaatoo['total'])) {
            $this->logger->warning('Before loading products you must load product categories');

            if (is_callable($cl)) {
                $cl('Before loading products you must load product categories');
            }

            return;
        }

        foreach ($this->storeManager->getStores() as $store) {
            if (empty($this->storeMap->getStoreToMaatoo($store->getId())) ||
                $this->storeMap->getStoreToMaatoo($store->getId()) === ''
            ) {
                $this->logger->warning(sprintf('store #%s not synced to maatoo yet.', $store->getId()));

                continue;
            }

            $storeId = $store->getId();

            $this->logger->info(
                sprintf(
                    'Begin syncing products to maatoo for store: %s (#%s)',
                    $store->getName(),
                    $storeId
                )
            );

            $collection = $this->collectionFactory->create();
            $collection->addStoreFilter($store);

            /** @var Product $product */
            foreach ($collection as $item) {
                try {
                    $product = $this->productRepository->getById($item->getId(), false, $storeId);
                } catch (\Exception) {
                    $this->logger->info(sprintf('Product with id: %s is not found', $item->getId()));
                    continue;
                }

                /** @var Sync $sync */
                $sync = $this->syncRepository->getByParam([
                    'entity_id'   => $product->getId(),
                    'entity_type' => SyncInterface::TYPE_PRODUCT,
                    'store_id'    => $storeId,
                ]);

                if ($sync->getData('status') == SyncInterface::STATUS_SYNCHRONIZED) {
                    continue;
                }

                // Get parent product
                $parent = $this->productSyncHelper->getParent($product->getId(), $storeId);

                $parameters = $this->productSyncHelper->getPreparedParameters($product, $storeId);

                if (!$parameters) {
                    continue;
                }

                // Price
                $priceData = $this->productSyncHelper->getPreparedPriceData($product);
                $parameters = array_merge($parameters, $priceData);

                // Image
                $parameters['imageUrl'] = $this->productSyncHelper->getPreparedImageUrlData($product, $store, $parent);

                // Url
                $parameters['url'] = $this->productSyncHelper->getPreparedProductUrl($product, $parent);

                // Visibility
                $parameters['isVisible'] = $this->productSyncHelper->getVisibilityStatus($product);

                // Stock
                $stock = $this->stockRegistry->getStockItem($product->getId(), $storeId);
                $parameters['inventoryQuantity'] = (int)$stock->getQty();
                $parameters['backorders'] = $stock->getBackorders();

                // Handle children of configurable products
                if ($parent) {
                    $parentMaatooData = $this->productSyncHelper->getProductParentParameter($parent->getId(), $storeId);

                    if (!$parentMaatooData) {
                        continue;
                    }

                    // Get maatoo id of parent product
                    $parameters['productParent'] = (int)$parentMaatooData['maatoo_id'];
                }

                if (empty($sync->getStatus()) || $sync->getStatus() == SyncInterface::STATUS_EMPTY) {
                    $this->executesCreateProductRequest($product, $parameters, $sync, $storeId, $cl);
                } elseif ($sync->getStatus() == SyncInterface::STATUS_UPDATED) {
                    $parameters['id'] = $sync->getMaatooId();
                    $productsDataToUpdate[$parameters['id']] = $parameters;
                    $syncDataToUpdate[$parameters['id']] = $sync;
                }
            }

            // Update products via batch
            $this->executesUpdateProductsRequests($productsDataToUpdate, $syncDataToUpdate, $cl);

            // Delete entities
            $this->executesDeleteProductsRequests($storeId, $cl);
        }

        $this->logger->info('Finished syncing products to maatoo.');
    }

    /**
     * Executes a request to create a new product
     */
    private function executesCreateProductRequest(
        ProductInterface $product,
        array            $parameters,
        Sync             $sync,
        int              $storeId,
        ?\Closure        $cl = null
    ): void {
        $result = $this->adapter->makeRequest('products/new', $parameters, 'POST') ?? [];

        $this->logSyncResponseData(
            sprintf('Added product #%s %s to maatoo', $product->getId(), $product->getName()),
            $cl
        );

        if (isset($result['product']['id'])) {
            $this->productSyncHelper->updateSyncData($sync, $result['product']['id'], $product->getId(), $storeId);
        }
    }

    /**
     * Executes update requests for products and sync data, logging the updates and calling a closure if provided.
     */
    private function executesUpdateProductsRequests(
        array     $productsDataToUpdate,
        array     $syncDataToUpdate,
        ?\Closure $cl = null
    ): void {
        $productsDataToUpdateList = array_chunk($productsDataToUpdate, SyncServiceInterface::BATCH_SIZE_LIMIT, true);

        foreach ($productsDataToUpdateList as $productsList) {
            $result = $this->adapter->makeRequest(
                'products/batch/edit',
                $productsList,
                'PATCH'
            ) ?? null;

            if (isset($result['products'])) {
                foreach ($result['products'] as $productData) {
                    $syncData = $syncDataToUpdate[$productData['id']];

                    $this->productSyncHelper->updateSyncData(
                        $syncData,
                        $productData['id'],
                        $syncData->getEntityId(),
                        $syncData->getStoreId(),
                    );

                    $this->logSyncResponseData(
                        sprintf(
                        'Updated product #%s %s in maatoo',
                        $syncData->getEntityId(),
                        $productData['title'] ?? ''),
                        $cl
                    );
                }
            }

            if (isset($result['errors'])) {
                foreach ($result['errors'] as $maatooId => $errorData) {
                    $syncData = $productsDataToUpdate[$maatooId];

                    $this->logSyncResponseData(
                        sprintf(
                            'An error occurred while updating the product #%s %s in maatoo: %s',
                            $syncData['externalProductId'] ?? '',
                            $syncData['title'] ?? '',
                            $errorData['message'] ?? ''
                        ),
                        $cl
                    );
                }
            }
        }
    }

    /**
     * The function executes delete requests for products in the Maatoo system based on certain criteria.
     */
    private function executesDeleteProductsRequests(int $storeId, ?\Closure $cl = null): void
    {
        $this->searchCriteriaBuilder->addFilter('entity_type', SyncInterface::TYPE_PRODUCT);
        $this->searchCriteriaBuilder->addFilter('status', SyncInterface::STATUS_DELETED);
        $this->searchCriteriaBuilder->addFilter('store_id', $storeId);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $collectionForDelete = $this->syncRepository->getList($searchCriteria);

        foreach ($collectionForDelete as $item) {
            $this->adapter->makeRequest(sprintf('products/%s/delete', $item->getMaatooId()), [], 'DELETE');
            $this->logger->info('Deleted product in maatoo with id #' . $item->getMaatooId());

            if (is_callable($cl)) {
                $cl('Deleted product #' . $item->getMaatooId() . ' in maatoo');
            }

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
