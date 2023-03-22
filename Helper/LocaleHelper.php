<?php

namespace Maatoo\Maatoo\Helper;

use Magento\Directory\Helper\Data;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Class LocaleHelper
 *
 * @package Maatoo\Maatoo\Helper
 */
class LocaleHelper extends AbstractHelper
{

    /**
     * Get store view language
     *
     * @param $storeViewId
     * @return string
     */
    public function getStoreViewLocale($storeViewId): string
    {
        return $this->scopeConfig->getValue(
            Data::XML_PATH_DEFAULT_LOCALE,
            ScopeInterface::SCOPE_STORE,
            $storeViewId
        );
    }
}
