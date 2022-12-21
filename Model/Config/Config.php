<?php

namespace Maatoo\Maatoo\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ScopeInterface
     */
    private $scopeStore;

    /**
     * @var XML path of Enable module
     */
    const MAATOO_MODULE_ENABLE = 'maatoo/general/active';

    /**
     * @var XML path of Maatoo URL
     */
    const MAATOO_URL_PATH = 'maatoo/general/url';

    /**
     * @var XML path of Maatoo User
     */
    const MAATOO_USER_PATH = 'maatoo/general/user';

    /**
     * @var XML path of Maatoo Password
     */
    const MAATOO_PASSWORD_PATH = 'maatoo/general/password';

    /**
     * @var XML path of Maatoo website active
     */
    const MAATAA_WEBSITE_ACTIVE = 'maatoo/website/website_active';

    /**
     * @var XML path of Maatoo 'birthday in checkout' config
     */
    const MAATOO_BIRTHDAY_IN_CHECKOUT = 'maatoo/website/birthday_in_checkout';

    /**
     * @var XML path of Maatoo Stores
     */
    const MAATOO_ALLOWED_STORE = 'maatoo/website/allowed_store';

    /**
     * @var XML path of Maatoo Debug into log file
     */
    const MAATOO_DEBUG_ENABLED = 'maatoo/general/debug_enabled';

    /**
     * @var XML path of Maatoo Order lifetime
     */
    const MAATOO_ORDER_LIFETIME = 'maatoo/order/lifetime';

    /**
     * @var XML path of Maatoo Marketing Opt In
     */
    const MAATOO_OPT_IN = 'maatoo/website/opt_in';

    /**
     * @var XML path of Maatoo Marketing Opt In Text
     */
    const MAATOO_OPT_IN_TEXT = 'maatoo/website/opt_in_text';

    /**
     * @var XML path of Maatoo Disable Default Newsletter Confirmation Email
     */
    const MAATOO_NEWSLETTER_CONFIRMATION_EMAIL = 'maatoo/newsletter/disable_newsletter_email';

    /**
     * Background constructor
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->scopeStore = ScopeInterface::SCOPE_STORE;
    }

    /**
     * @return bool
     */
    public function isModuleEnable(): bool
    {
        $_matoUrl = $this->getMaatooUrl();
        return $this->scopeConfig->isSetFlag(self::MAATOO_MODULE_ENABLE, $this->scopeStore) && !empty($_matoUrl);
    }

    /**
     * @return string
     */
    public function getMaatooUrl(): string
    {
        $url = $this->scopeConfig->getValue(self::MAATOO_URL_PATH, $this->scopeStore);
        if (substr($url, -1) != '/') {
            $url = $url . '/';
        }
        return $url;
    }

    /**
     * @return string
     */
    public function getMaatooUser(): string
    {
        return $this->scopeConfig->getValue(self::MAATOO_USER_PATH, $this->scopeStore);
    }

    /**
     * @return string
     */
    public function getMaatooPassword(): string
    {
        return $this->scopeConfig->getValue(self::MAATOO_PASSWORD_PATH, $this->scopeStore);
    }

    /**
     * Get if the log is enabled for connector.
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::MAATOO_DEBUG_ENABLED, $this->scopeStore);
    }

    /**
     * @return string
     */
    public function getWorkspaceName()
    {
        $url = parse_url($this->getMaatooUrl(), PHP_URL_HOST);
        if (!empty($url)) {
            $url = str_replace('www.', '', $url);
            $explode = explode('.', $url);
            $workspacename = $explode[0];
            return (string)$workspacename;
        }
        return '';
    }

    /**
     * @return string
     */
    public function getOrderLifetime()
    {
        return $this->scopeConfig->getValue(self::MAATOO_ORDER_LIFETIME, $this->scopeStore);
    }

    /**
     * @return string
     */
    public function getOptIn()
    {
        return $this->scopeConfig->getValue(self::MAATOO_OPT_IN, $this->scopeStore);
    }

    /**
     * @return string
     */
    public function getOptInText()
    {
        return $this->scopeConfig->getValue(self::MAATOO_OPT_IN_TEXT, $this->scopeStore);
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isWebsiteAllowed($websiteId)
    {
        return $this->scopeConfig->isSetFlag(
            \Maatoo\Maatoo\Model\Config\Config::MAATAA_WEBSITE_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }


    /**
     * @param $websiteId
     * @return bool
     */
    public function isAllowedBirthdayInCheckout($websiteId = null)
    {
        return $this->scopeConfig->getValue(
            \Maatoo\Maatoo\Model\Config\Config::MAATOO_BIRTHDAY_IN_CHECKOUT,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES
        );
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isStoreAllowed($websiteId)
    {
        return $this->scopeConfig->isSetFlag(
            \Maatoo\Maatoo\Model\Config\Config::MAATOO_ALLOWED_STORE,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * @return bool
     */
    public function isNewsletterConfirmationEmailDisabled()
    {
        return $this->scopeConfig->getValue(self::MAATOO_NEWSLETTER_CONFIRMATION_EMAIL, $this->scopeStore);
    }

    /**
     * @return mixed
     */
    public function getAllowedStores($websiteId = null)
    {
        return $this->scopeConfig->getValue(
            \Maatoo\Maatoo\Model\Config\Config::MAATOO_ALLOWED_STORE,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }


}
