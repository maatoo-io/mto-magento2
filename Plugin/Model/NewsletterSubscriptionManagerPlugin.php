<?php

namespace Maatoo\Maatoo\Plugin\Model;

use Maatoo\Maatoo\Helper\LocaleHelper;
use Maatoo\Maatoo\Model\Config\Config;
use Maatoo\Maatoo\Adapter\AdapterInterface;
use Maatoo\Maatoo\Model\StoreConfigManager;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Newsletter\Controller\Subscriber\NewAction;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Newsletter\Model\SubscriptionManager;
use Magento\Store\Model\StoreManagerInterface;
use Maatoo\Maatoo\Logger\Logger;

class NewsletterSubscriptionManagerPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreConfigManager $storeConfigManager
     */
    private StoreConfigManager $storeConfigManager;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var AdapterInterface $adapter
     */
    private AdapterInterface $adapter;

    /**
     * @var CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    private $logger;

    /**
     * @var LocaleHelper
     */
    private $localeHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Config $config,
        StoreConfigManager $storeConfigManager,
        AdapterInterface $adapter,
        CookieManagerInterface $cookieManager,
        StoreManagerInterface $storeManager,
        Logger $logger,
        LocaleHelper $localeHelper)
    {
        $this->scopeConfig = $scopeConfig;
        $this->config = $config;
        $this->storeConfigManager = $storeConfigManager;
        $this->adapter = $adapter;
        $this->cookieManager = $cookieManager;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->localeHelper = $localeHelper;

    }


    /**
     * @param Magento\Newsletter\Model\SubscriptionManager $action
     * @param $result
     *
     * @return mixed
     */
    public function afterSubscribe(SubscriptionManager $subscriber, $result, $email, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);

        $leadId = $this->cookieManager->getCookie('mtc_id');
        $data = [
            'email' => $email,
            'preferred_locale' => $this->localeHelper->getStoreViewLocale($store->getId())
        ];

        $data['tags'] = $this->storeConfigManager->getTags($store);

        if (!empty($leadId)) {
            $this->adapter->makeRequest('contacts/' . $leadId . '/edit', $data, 'PATCH');
        } else {
            $this->adapter->makeRequest('contacts/new', $data, 'POST');
        }


        return $result;
    }

}
