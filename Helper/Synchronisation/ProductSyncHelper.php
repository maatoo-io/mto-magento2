<?php

namespace Maatoo\Maatoo\Helper\Synchronisation;

use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Model\StoreConfigManager;
use Maatoo\Maatoo\Model\StoreMap;
use Maatoo\Maatoo\Model\Sync;
use Maatoo\Maatoo\Model\SyncRepository;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResourceModel;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Class ProductSyncHelper
 *
 * @package Maatoo\Maatoo\Helper\Synchronisation
 */
class ProductSyncHelper
{
    /**
     * @var ConfigurableResourceModel
     */
    private $configurableProductType;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreMap
     */
    private $storeMap;

    /**
     * @var SyncRepository
     */
    private $syncRepository;

    /**
     * @var CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * ProductSyncHelper constructor.
     */
    public function __construct(
        ConfigurableResourceModel  $configurableProductType,
        ProductRepositoryInterface $productRepository,
        StoreMap                   $storeMap,
        SyncRepository             $syncRepository,
        CollectionFactory          $categoryCollectionFactory
    ) {
        $this->configurableProductType = $configurableProductType;
        $this->productRepository = $productRepository;
        $this->storeMap = $storeMap;
        $this->syncRepository = $syncRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Retrieves the parent product of a given child product in a configurable product type.
     */
    public function getParent(int $productId, int $storeId): ?ProductInterface
    {
        $parentIds = $this->configurableProductType->getParentIdsByChild($productId);
        $parent = null;

        foreach ($parentIds as $id) {
            $parent = $this->productRepository->getById($id, false, $storeId);

            if ($parent->getTypeId() == Configurable::TYPE_CODE) {
                break;
            } else {
                $parent = null;
            }
        }

        return $parent;
    }

    /**
     * Retrieves the regular price and special price of a product
     */
    public function getPreparedPriceData(ProductInterface $product): array
    {
        $parameters = [];

        // Simple product
        $regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getValue() ?? 0;
        $specialPrice = $product->getPriceInfo()->getPrice('special_price')->getValue() ?? 0;

        // Configurable product
        if ($product->getTypeId() == 'configurable') {
            $basePrice = $product->getPriceInfo()->getPrice('regular_price');

            $regularPrice = $basePrice->getMinRegularAmount()->getValue();
            $specialPrice = $product->getFinalPrice();
        }

        // Bundle product
        if ($product->getTypeId() == 'bundle') {
            $regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue();
            $specialPrice = $product->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
        }

        // Grouped product
        if ($product->getTypeId() == 'grouped') {
            $usedProds = $product->getTypeInstance()->getAssociatedProducts($product);
            foreach ($usedProds as $child) {
                if ($child->getId() != $product->getId()) {
                    $regularPrice += $child->getPrice();
                    $specialPrice += $child->getFinalPrice();
                }
            }
        }

        if ($specialPrice && $specialPrice < $regularPrice) {
            $parameters['price'] = number_format($specialPrice, 2, '.', '');
            $parameters['regularPrice'] = number_format($regularPrice, 2, '.', '');
        } else {
            $parameters['price'] = number_format($regularPrice, 2, '.', '');
            $parameters['regularPrice'] = '';
        }

        return $parameters;
    }

    /**
     * Returns the URL of the image associated with a product
     */
    public function getPreparedImageUrlData(
        ProductInterface $product,
        StoreInterface   $store,
        ProductInterface $parent = null
    ): string {
        $imageUrl = '';

        if ($product->getImage() && $product->getImage() != 'no_selection') {
            $filePath = 'catalog/product/' . ltrim($product->getImage(), '/');
            $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true) . $filePath;
        } elseif ($product->getSmallImage() && $product->getSmallImage() != 'no_selection') {
            $filePath = 'catalog/product/' . ltrim($product->getSmallImage(), '/');
            $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true) . $filePath;
        } elseif ($parent && $parent->getImage() && $parent->getImage() != 'no_selection') {
            $filePath = 'catalog/product/' . ltrim($parent->getImage(), '/');
            $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true) . $filePath;
        }

        return $imageUrl;
    }

    /**
     * Prepares an array of parameters for a product to be synced with a maatoo system
     */
    public function getPreparedParameters(ProductInterface $product, int $storeId): array
    {
        $parameters = [];
        $categoryIds = $product->getCategoryIds();

        // Don't allow empty categories
        if (empty($categoryIds)) {
            return $parameters;
        }

        $categoryId = 0;
        $level = 0;
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addFieldToFilter('entity_id', $categoryIds);

        /** @var CategoryInterface $category */
        foreach ($categoryCollection as $category) {
            if ($category->getLevel() > $level) {
                $categoryId = $category->getId();
                $level = $category->getLevel();
            }
        }

        $maatooSyncCategoryRow = $this->syncRepository->getRow([
            'entity_id'   => $categoryId,
            'entity_type' => SyncInterface::TYPE_CATEGORY,
            'store_id'    => $storeId,
        ]);

        if (!isset($maatooSyncCategoryRow['maatoo_id'])) {
            return $parameters;
        }

        $maatooCategoryId = $maatooSyncCategoryRow['maatoo_id'];

        return [
            'store'                 => $this->storeMap->getStoreToMaatoo($storeId),
            'category'              => $categoryIds[0],
            'externalProductId'     => $product->getId(),
            'title'                 => $product->getName(),
            'description'           => $product->getDescription() ?? '',
            'sku'                   => $product->getSku(),
            'productCategory'       => $maatooCategoryId,
            'externalDatePublished' => $product->getCreatedAt(),
        ];
    }

    /**
     * The function checks if a product is visible and enabled.
     */
    public function getVisibilityStatus(Product $product): bool
    {
        if ($product->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE ||
            $product->getStatus() == Status::STATUS_DISABLED
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns a prepared product URL based on the given product.
     */
    public function getPreparedProductUrl(Product $product, ?ProductInterface $parent): string
    {
        if (!$parent) {
            return $product->getProductUrl();
        }

        $tailUrl = '';
        $options = $parent->getTypeInstance()->getConfigurableAttributesAsArray($parent);

        foreach ($options as $option) {
            if (strlen($tailUrl)) {
                $tailUrl .= '&';
            } else {
                $tailUrl .= '?';
            }

            $tailUrl .= sprintf('%s=%s', $option['attribute_code'], $product->getData($option['attribute_code']));
        }

        return sprintf('%s%s', $parent->getProductUrl(), $tailUrl);
    }

    /**
     * The function retrieves the parent parameter of a product based on its ID and store ID.
     */
    public function getProductParentParameter(int $parentId, int $storeId): array
    {
        $maatooSyncParentProductRow = $this->syncRepository->getRow([
            'entity_id'   => $parentId,
            'entity_type' => SyncInterface::TYPE_PRODUCT,
            'store_id'    => $storeId,
        ]);

        if (empty($maatooSyncParentProductRow['maatoo_id'])) {
            return [];
        }

        return $maatooSyncParentProductRow;
    }

    /**
     * Updates the sync data
     */
    public function updateSyncData(Sync $sync, int $maatooId, int $entityId, int $storeId): void
    {
        $sync->setStatus(SyncInterface::STATUS_SYNCHRONIZED);
        $sync->setMaatooId($maatooId);
        $sync->setEntityId($entityId);
        $sync->setStoreId($storeId);
        $sync->setEntityType(SyncInterface::TYPE_PRODUCT);
        $this->syncRepository->save($sync);
    }
}
