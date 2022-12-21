<?php

namespace Maatoo\Maatoo\Model\Synchronization;

use Maatoo\Maatoo\Api\Data\SyncInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;

/**
 * Class Category
 * @package Maatoo\Maatoo\Model\Synchronization
 */
class Category
{
    /**
     * @var \Maatoo\Maatoo\Adapter\AdapterInterface
     */
    private $adapter;


    /**
     * @var \Maatoo\Maatoo\Model\StoreConfigManager
     */
    private $storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var \Magento\Catalog\Helper\Category
     */
    private $categoryHelper;

    /**
     * @var \Maatoo\Maatoo\Model\StoreMap
     */
    private $storeMap;

    /**
     * @var \Maatoo\Maatoo\Model\SyncRepository
     */
    private $syncRepository;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Category constructor.
     * @param \Maatoo\Maatoo\Model\StoreConfigManager $storeManager
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collectionFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param \Magento\Catalog\Helper\Category $categoryHelper
     * @param \Magento\Framework\UrlInterface $urlBilder
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Maatoo\Maatoo\Adapter\AdapterInterface $adapter
     * @param \Maatoo\Maatoo\Model\StoreMap $storeMap
     * @param \Maatoo\Maatoo\Model\SyncRepository $syncRepository
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Maatoo\Maatoo\Model\StoreConfigManager $storeManager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collectionFactory,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Framework\UrlInterface $urlBilder,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Maatoo\Maatoo\Adapter\AdapterInterface $adapter,
        \Maatoo\Maatoo\Model\StoreMap $storeMap,
        \Maatoo\Maatoo\Model\SyncRepository $syncRepository,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->categoryHelper = $categoryHelper;
        $this->adapter = $adapter;
        $this->storeMap = $storeMap;
        $this->syncRepository = $syncRepository;
        $this->urlBilder = $urlBilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * @param \Closure|null $cl
     */
    public function sync(\Closure $cl = null)
    {
        $this->logger->info("Begin syncing categories to maatoo.");
        $parameters = [];
        $storesMaatoo = $this->adapter->makeRequest('stores', $parameters, 'GET');
        if(empty($storesMaatoo['total'])) {
            $this->logger->warning('Before loading categories you must load stores');
            if (is_callable($cl)) {
                $cl('Before loading categories you must load stores');
            }
            return ;
        }

        //$categoryMaatoo = $this->adapter->makeRequest('product-categories', $parameters, 'GET');

        foreach ($this->storeManager->getStores() as $store)
        {
            $collection = $this->collectionFactory->create();
            $collection
                ->getSelect()
                ->joinLeft(
                    ['sync' => $collection->getTable('maatoo_sync')],
                    '(e.entity_id = sync.entity_id) AND (sync.entity_type="' . SyncInterface::TYPE_CATEGORY . '") AND (sync.store_id="' . $store->getId() . '")',
                    [
                        'sync_entity_type' => 'sync.entity_type',
                        'sync_status' => 'sync.status',
                        'sync_maatoo_id' => 'sync.maatoo_id',
                        'store_id' => 'sync.store_id'
                    ])
            ;

            foreach ($collection as $item) {
                if($item->getData('sync_status')==1) {
                    //continue;
                }
                /** @var \Magento\Catalog\Api\Data\CategoryInterface $category */
                $category = $this->categoryRepository->get($item->getId(), $store->getId());
                if($category->getStoreId() != $store->getId()){
                    continue;
                }
                if (!empty($category->getId()) && !empty($category->getUrlKey())) {
                    $parameters = [
                        'store' => $this->storeMap->getStoreToMaatoo($store->getId()),
                        "name" => $category->getName(),
                        "alias" => $category->getUrlKey(),
                        "url" => $this->categoryHelper->getCategoryUrl($category),
                        "externalCategoryId" => $category->getId(),
                    ];

                    $result = [];

                    if (empty($item->getData('sync_status')) || $item->getData('sync_status') == SyncInterface::STATUS_EMPTY) {
                        $result = $this->adapter->makeRequest('product-categories/new', $parameters, 'POST');
                        $this->logger->info('Added category #' . $item->getId() . ' ' . $category->getName() . ' to maatoo');
                        if(is_callable($cl)) {
                            $cl('Added category #' . $item->getId() . ' ' . $category->getName() . ' to maatoo');
                        }
                    } elseif ($item->getData('sync_status') == SyncInterface::STATUS_UPDATED) {
                        $result = $this->adapter->makeRequest('product-categories/' . $item->getData('sync_maatoo_id') . '/edit', $parameters, 'PATCH');
                        $self->logger->info('Updated category #' . $item->getId() . ' ' . $category->getName() . ' in maatoo');
                        if(is_callable($cl)) {
                            $cl('Updated category #' . $item->getId() . ' ' . $category->getName() . ' in maatoo');
                        }
                    }

                    if(isset($result['category']['id'])) {
                        $param = [
                            'entity_id' => $item->getId(),
                            'entity_type' => SyncInterface::TYPE_CATEGORY,
                            'store_id' => $store->getId(),
                        ];
                        // @var \Maatoo\Maatoo\Model\Sync $sync
                        $sync = $this->syncRepository->getByParam($param);
                        $sync->setStatus(SyncInterface::STATUS_SYNCHRONIZED);
                        $sync->setMaatooId($result['category']['id']);
                        $sync->setEntityId($item->getId());
                        $sync->setStoreId($store->getId());
                        $sync->setEntityType(SyncInterface::TYPE_CATEGORY);
                        $this->syncRepository->save($sync);
                    }
                }
            }

            // Delete entity
            $this->searchCriteriaBuilder->addFilter('entity_type', SyncInterface::TYPE_CATEGORY);
            $this->searchCriteriaBuilder->addFilter('status', SyncInterface::STATUS_DELETED);
            $this->searchCriteriaBuilder->addFilter('store_id', $store->getId());
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $collectionForDelete = $this->syncRepository->getList($searchCriteria);
            foreach ($collectionForDelete as $item)
            {
                $result = $this->adapter->makeRequest('product-categories/' . $item->getMaatooId() . '/delete', [], 'DELETE');
                $this->logger->info('Deleted category from maatoo with id #' . $item->getMaatooId());
                if(is_callable($cl)) {
                    $cl('Deleted category from maatoo with id #' . $item->getMaatooId());
                }

                $this->syncRepository->delete($item);
            }
        }
        $this->logger->info("Finished syncing categories to maatoo.");
    }
}
