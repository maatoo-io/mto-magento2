<?php

namespace Maatoo\Maatoo\Model\Synchronization;

use Maatoo\Maatoo\Api\Data\SyncInterface;

/**
 * Class Product
 * @package Maatoo\Maatoo\Model\Synchronization
 */
class Product
{
    const DOWNLOADABLE = 'downloadable';

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $collectionFactory;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBilder;
    /**
     * @var \Magento\Catalog\Helper\Product
     */
    private $productHelper;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var \Maatoo\Maatoo\Model\StoreConfigManager
     */
    private $storeManager;
    /**
     * @var \Maatoo\Maatoo\Adapter\AdapterInterface
     */
    private $adapter;
    /**
     * @var \Maatoo\Maatoo\Model\StoreMap
     */
    private $storeMap;
    /**
     * @var \Maatoo\Maatoo\Model\SyncRepository
     */
    private $syncRepository;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;

    private $syncCategory;
    /**
     * @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     */
    private $configurableProductType;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Product constructor.
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Framework\UrlInterface $urlBilder
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Maatoo\Maatoo\Model\StoreConfigManager $storeManager
     * @param \Maatoo\Maatoo\Adapter\AdapterInterface $adapter
     * @param \Maatoo\Maatoo\Model\StoreMap $storeMap
     * @param \Maatoo\Maatoo\Model\SyncRepository $syncRepository
     * @param Category $syncCategory
     * @param \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurableProductType
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Framework\UrlInterface $urlBilder,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Maatoo\Maatoo\Model\StoreConfigManager $storeManager,
        \Maatoo\Maatoo\Adapter\AdapterInterface $adapter,
        \Maatoo\Maatoo\Model\StoreMap $storeMap,
        \Maatoo\Maatoo\Model\SyncRepository $syncRepository,
        \Maatoo\Maatoo\Model\Synchronization\Category $syncCategory,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurableProductType,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Psr\Log\LoggerInterface $logger

    )
    {
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->productHelper = $productHelper;
        $this->adapter = $adapter;
        $this->storeMap = $storeMap;
        $this->syncRepository = $syncRepository;
        $this->urlBilder = $urlBilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->syncCategory = $syncCategory;
        $this->configurableProductType = $configurableProductType;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * @param \Closure|null $cl
     */
    public function sync(\Closure $cl = null)
    {
        $this->logger->info("Begin syncing products to maatoo.");
        $this->syncCategory->sync($cl);

        $parameters = [];
        $categoryMaatoo = $this->adapter->makeRequest('product-categories', $parameters, 'GET');
        if (empty($categoryMaatoo['total'])) {
            $this->logger->warning('Before loading products you must load product categories');
            if (is_callable($cl)) {
                $cl('Before loading products you must load product categories');
            }
            return;
        }

        foreach ($this->storeManager->getStores() as $store) {
            $collection = $this->collectionFactory->create();
            $collection->addStoreFilter($store);

            /** @var \Magento\Catalog\Model\Product $product */
            foreach ($collection as $item) {
                $product = $this->productRepository->getById($item->getId(), false, $store->getId());

                /** @var \Maatoo\Maatoo\Model\Sync $sync */
                $sync = $this->syncRepository->getByParam([
                    'entity_id' => $product->getId(),
                    'entity_type' => SyncInterface::TYPE_PRODUCT,
                    'store_id' => $store->getId(),
                ]);

                if ($sync->getData('status') == SyncInterface::STATUS_SYNCHRONIZED) {
                    continue;
                }

                // Get children products
                $productChildren = [];
                if($product->getTypeId() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                    $childProducts = $product->getTypeInstance()->getChildrenIds($product->getId());
                    if (count($childProducts[0])) {
                        foreach ($childProducts[0] as $childId) {
                            $productChildren[] = $this->productRepository->getById($childId, false, $store->getId());
                        }
                    }
                }

                // Get parent product
                $parent = $this->getParent($product->getId(), $store->getId());


                $price = $this->productHelper->getPrice($product);
                $categoryIds = $product->getCategoryIds();

                // Don't allow empty categories
                if (empty($categoryIds)) {
                    continue;
                }

                $categoryId = 0;
                $level = 0;
                $categoryCollection = $this->categoryCollectionFactory->create();
                $categoryCollection->addFieldToFilter('entity_id', $categoryIds);
                /** @var \Magento\Catalog\Api\Data\CategoryInterface $_category */
                foreach ($categoryCollection as $_category) {
                    if ($_category->getLevel() > $level) {
                        $categoryId = $_category->getId();
                        $level = $_category->getLevel();
                    }
                }

                $maatooSyncCategoryRow = $this->syncRepository->getRow([
                    'entity_id' => $categoryId,
                    'entity_type' => SyncInterface::TYPE_CATEGORY,
                    'store_id' => $store->getId()]);

                if (!isset($maatooSyncCategoryRow['maatoo_id'])) {
                    continue;
                }

                $maatooCategoryId = $maatooSyncCategoryRow['maatoo_id'];

                $parameters = [
                    'store' => $this->storeMap->getStoreToMaatoo($store->getId()),
                    "category" => $categoryIds[0],
                    "externalProductId" => $product->getId(),
                    "title" => $product->getName(),
                    "description" => $product->getDescription(),
                    "sku" => $product->getSku(),
                    "productCategory" => $maatooCategoryId,
                    "externalDatePublished" => $product->getCreatedAt()
                ];

                // Price
                foreach ($productChildren as $variant) {
                    if ($variant && $variant->getId() != $product->getId()) {
                        //$variantPrice = $this->_getProductPrice($variant);
                        $variantPrice = $this->productHelper->getPrice($variant);
                        if ($price) {
                            if ($variantPrice < $price) {
                                $price = $variantPrice;
                            }
                        } else {
                            $price = $variantPrice;
                        }
                    }
                }
                $parameters["price"] = number_format($price, 2, '.', '');

                // Image
                if ($product->getImage() && $product->getImage()!='no_selection') {
                    $filePath = 'catalog/product/'.ltrim($product->getImage(), '/');
                    $parameters["imageUrl"] = $this->getBaseUrl(
                            $store->getId(),
                            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                        ).$filePath;
                } else {
                    if ($parent && $parent->getImage() && $parent->getImage()!='no_selection') {
                        $filePath = 'catalog/product/'.ltrim($parent->getImage(), '/');
                        $parameters["imageUrl"] = $this->getBaseUrl(
                                $store->getId(),
                                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                            ).$filePath;
                    }
                }

                // Url
                $parameters["url"] = $product->getProductUrl();
                if ($parent) {
                    $tailUrl = '';
                    $options = $parent->getTypeInstance()->getConfigurableAttributesAsArray($parent);
                    foreach ($options as $option) {
                        if (strlen($tailUrl)) {
                            $tailUrl .= '&';
                        } else {
                            $tailUrl .= '?';
                        }
                        $tailUrl .= $option['attribute_code'] . "=" . $product->getData($option['attribute_code']);
                    }
                    $parameters["url"] = $parent->getProductUrl() . $tailUrl;
                }

                // Visibility
                $parameters["isVisible"] = true;
                if ($product->getVisibility() == \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE) {
                    $parameters["isVisible"] = false;
                }

                // Stock
                $stock = $this->stockRegistry->getStockItem($product->getId(), $store->getId());
                $parameters["inventoryQuantity"] = (int)$stock->getQty();
                $parameters["backorders"] = $stock->getBackorders();

                // Handle children of configurable products
                if($parent) {
                    $maatooSyncParentProductRow = $this->syncRepository->getRow([
                        'entity_id' => $parent->getId(),
                        'entity_type' => SyncInterface::TYPE_PRODUCT,
                        'store_id' => $store->getId()
                    ]);

                    if (empty($maatooSyncParentProductRow['maatoo_id'])) {
                        continue;
                    }

                    // Get maatoo id of parent product
                    $parameters["productParent"] = (int)$maatooSyncParentProductRow['maatoo_id'];
                }

                $result = [];

                if (empty($sync->getData('status')) || $sync->getData('status') == SyncInterface::STATUS_EMPTY) {
                    $result = $this->adapter->makeRequest('products/new', $parameters, 'POST');
                    $this->logger->info('Added product #' . $product->getId() . ' ' . $product->getName() . ' to maatoo');
                    if (is_callable($cl)) {
                        $cl('Added product #' . $product->getId() . ' ' . $product->getName() . ' to maatoo');
                    }
                } elseif ($sync->getData('status') == SyncInterface::STATUS_UPDATED) {
                    $result = $this->adapter->makeRequest('products/' . $sync->getData('maatoo_id') . '/edit', $parameters, 'PATCH');
                    $this->logger->info('Updated product #' . $product->getId() . ' ' . $product->getName() . ' in maatoo');
                    if (is_callable($cl)) {
                        $cl('Updated product #' . $product->getId() . ' ' . $product->getName() . ' in maatoo');
                    }
                }

                if (isset($result['product']['id'])) {
                    /*$param = [
                        'entity_id' => $product->getId(),
                        'entity_type' => SyncInterface::TYPE_PRODUCT,
                        'store_id' => $store->getId(),
                    ];
                    // @var \Maatoo\Maatoo\Model\Sync $sync
                    $sync = $this->syncRepository->getByParam($param);*/
                    $sync->setStatus(SyncInterface::STATUS_SYNCHRONIZED);
                    $sync->setMaatooId($result['product']['id']);
                    $sync->setEntityId($product->getId());
                    $sync->setStoreId($store->getId());
                    $sync->setEntityType(SyncInterface::TYPE_PRODUCT);
                    $this->syncRepository->save($sync);
                }
            }

            // Delete entity
            $this->searchCriteriaBuilder->addFilter('entity_type', SyncInterface::TYPE_PRODUCT);
            $this->searchCriteriaBuilder->addFilter('status', SyncInterface::STATUS_DELETED);
            $this->searchCriteriaBuilder->addFilter('store_id', $store->getId());
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $collectionForDelete = $this->syncRepository->getList($searchCriteria);
            foreach ($collectionForDelete as $item) {
                $result = $this->adapter->makeRequest('products/' . $item->getMaatooId() . '/delete', [], 'DELETE');
                $this->logger->info('Deleted product in maatoo with id #' . $item->getMaatooId());
                if (is_callable($cl)) {
                    $cl('Deleted product #' . $item->getMaatooId() . ' in maatoo');
                }

                $this->syncRepository->delete($item);
            }
        }
        $this->logger->info("Finished syncing products to maatoo.");
    }

    protected function getBaseUrl($storeId, $type)
    {
        return $this->storeManager->getStoreById($storeId)->getBaseUrl($type, true);
    }

    protected function getParent($productId, $storeId)
    {
        //$parentIds =$this->_configurable->getParentIdsByChild($productId);
        $parentIds = $this->configurableProductType->getParentIdsByChild($productId);
        $parent = null;
        foreach ($parentIds as $id) {
            $parent = $this->productRepository->getById($id, false, $storeId);
            if ($parent->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                break;
            } else {
                $parent = null;
            }
        }
        return $parent;
    }
}
