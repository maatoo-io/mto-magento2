<?php

namespace Maatoo\Maatoo\Model;

/**
 * Class StoreConfigManager
 * @package Maatoo\Maatoo\Model
 */
class StoreConfigManager
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    private $config;


    /**
     * StoreConfigManager constructor.
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @param Config\Config $config
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Maatoo\Maatoo\Model\Config\Config $config
    )
    {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeRepository = $storeRepository;
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getStores()
    {
        $stores = [];
        foreach ($this->storeManager->getStores(false) as $store) {
            if ($this->config->isWebsiteAllowed($store->getWebsiteId())) {
                $storesAllowed = $this->config->getAllowedStores($store->getWebsiteId());
                if ($storesAllowed) {
                    $storesAllowed = explode(',', $storesAllowed);
                    if (in_array($store->getId(), $storesAllowed)) {
                        $stores[] = $store;
                    }
                }
            }
        }
        return $stores;
    }

    public function getStoreIds()
    {
        $stores = [];
        foreach($this->getStores() as $store)
        {
            $stores[] = $store->getId();
        }
        return $stores;
    }

    public function getStoreShortName(\Magento\Store\Api\Data\StoreInterface $store)
    {
        return preg_replace('#\W+#', '', trim(strtolower($store->getName())));
    }

    public function getTags($store)
    {
        return $this->getStoreShortName($store) . '-pending';
    }

    public function getStoreById($storeId) {
        return $this->storeManager->getStore($storeId);
    }

}
