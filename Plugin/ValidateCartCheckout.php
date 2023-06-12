<?php

namespace Maatoo\Maatoo\Plugin;

use Magento\Catalog\Model\Product\Type\AbstractType;

class ValidateCartCheckout
{
    public static $checker = false;

    /**
     * Check custom conditions to allow validate options on cart and checkout
     *
     * @param AbstractType $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundCheckProductBuyState(AbstractType $subject, \Closure $proceed, $product)
    {
        if (static::$checker) {
            $product->setSkipCheckRequiredOption(true);
        }

        return $proceed($product);
    }
}