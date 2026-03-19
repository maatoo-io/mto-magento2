<?php

namespace Maatoo\Maatoo\Model\Source;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class AllStores
 */
class AllStores implements OptionSourceInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->storeManager->getStores() as $store) {
            $options[] = [
                'value' => $store->getId(),
                'label' => $store->getName() . ' (' . $store->getCode() . ')',
            ];
        }

        return $options;
    }
}
