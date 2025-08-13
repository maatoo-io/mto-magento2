<?php

namespace Maatoo\Maatoo\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

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
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

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
     * Config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->scopeStore = ScopeInterface::SCOPE_STORE;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Checks if module is enabled and Maatoo URL is set
     */
    public function isModuleEnable(?int $websiteId = null): bool
    {
        $url = $this->getMaatooUrl($websiteId);

        return $this->scopeConfig->isSetFlag(
                self::MAATOO_MODULE_ENABLE,
                ScopeInterface::SCOPE_WEBSITES,
                $this->getScopeCode($websiteId)
            ) && !empty($url);
    }

    /**
     * Returns the Maatoo base URL with trailing slash
     */
    public function getMaatooUrl(?int $websiteId = null): string
    {
        $url = (string)$this->scopeConfig->getValue(
            self::MAATOO_URL_PATH,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );

        return rtrim($url, '/') . '/';
    }

    /**
     * Returns the Maatoo username
     */
    public function getMaatooUser(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::MAATOO_USER_PATH,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Returns the Maatoo password
     */
    public function getMaatooPassword(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::MAATOO_PASSWORD_PATH,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Checks if debug logging is enabled
     */
    public function isDebugEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::MAATOO_DEBUG_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Gets workspace name extracted from Maatoo URL
     */
    public function getWorkspaceName(?int $websiteId = null): string
    {
        $url = parse_url($this->getMaatooUrl($websiteId), PHP_URL_HOST);

        if (!empty($url)) {
            $url = str_replace('www.', '', $url);
            $explode = explode('.', $url);

            return $explode[0] ?? '';
        }

        return '';
    }

    /**
     * Returns order lifetime setting
     */
    public function getOrderLifetime(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::MAATOO_ORDER_LIFETIME,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Returns opt-in value
     */
    public function getOptIn(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::MAATOO_OPT_IN,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Returns opt-in text value
     */
    public function getOptInText(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::MAATOO_OPT_IN_TEXT,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Checks if current website is allowed (active)
     */
    public function isWebsiteAllowed(?int $websiteId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::MAATAA_WEBSITE_ACTIVE,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Checks if birthday input in checkout is enabled
     */
    public function isAllowedBirthdayInCheckout(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::MAATOO_BIRTHDAY_IN_CHECKOUT,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Checks if current store is allowed
     */
    public function isStoreAllowed(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::MAATOO_ALLOWED_STORE,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Checks if newsletter confirmation email is disabled
     */
    public function isNewsletterConfirmationEmailDisabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::MAATOO_NEWSLETTER_CONFIRMATION_EMAIL,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Returns allowed stores value from config
     *
     * @return mixed
     */
    public function getAllowedStores(?int $websiteId = null)
    {
        return $this->scopeConfig->getValue(
            self::MAATOO_ALLOWED_STORE,
            ScopeInterface::SCOPE_WEBSITES,
            $this->getScopeCode($websiteId)
        );
    }

    /**
     * Gets website code by ID
     */
    private function getScopeCode(?int $websiteId): ?string
    {
        if ($websiteId === null) {
            return null;
        }

        try {
            return $this->storeManager->getWebsite($websiteId)->getCode();
        } catch (LocalizedException $e) {
            $this->logger->warning(sprintf(
                'Invalid websiteId passed to Config::getScopeCode(): %s (%s)',
                $websiteId,
                $e->getMessage()
            ));

            return null;
        }
    }
}
