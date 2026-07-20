<?php

namespace Maatoo\Maatoo\Observer;

use Magento\Framework\Event\ObserverInterface;
use Maatoo\Maatoo\Api\Data\SyncInterface;

/**
 * Class OrderSaveObserver
 * @package Maatoo\Maatoo\Observer
 */
class OrderSaveObserver implements ObserverInterface
{
    /**
     * @var \Maatoo\Maatoo\Model\StoreConfigManager
     */
    private $storeManager;

    /**
     * @var \Maatoo\Maatoo\Model\SyncFactory
     */
    private $syncFactory;

    /**
     * @var \Maatoo\Maatoo\Model\ResourceModel\Sync
     */
    private $syncResource;

    /**
     * EntitySaveObserver constructor.
     * @param \Maatoo\Maatoo\Model\SyncFactory $syncFactory
     * @param \Maatoo\Maatoo\Model\ResourceModel\Sync $syncResource
     * @param $entityType
     */
    public function __construct(
        \Maatoo\Maatoo\Model\SyncFactory $syncFactory,
        \Maatoo\Maatoo\Model\ResourceModel\Sync $syncResource,
        \Maatoo\Maatoo\Model\StoreConfigManager $storeManager
    )
    {
        $this->syncFactory = $syncFactory;
        $this->syncResource = $syncResource;
        $this->storeManager = $storeManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getData('data_object');

        $entityId = (int)$order->getQuoteId();
        $storeId = (int)$order->getStoreId();

        if (!in_array($storeId, $this->storeManager->getStoreIds(), true)) {
            return;
        }

        $statusSync = SyncInterface::STATUS_EMPTY;

        /** @var \Maatoo\Maatoo\Model\Sync $sync */
        $sync = $this->syncFactory->create();
        $this->syncResource->loadByType($sync, $entityId, SyncInterface::TYPE_ORDER, $storeId);

        if ($sync->getSyncId()) {
            if ($sync->getStatus() == SyncInterface::STATUS_SYNCHRONIZED) {
                $statusSync = SyncInterface::STATUS_UPDATED;
            } else {
                $statusSync = $sync->getStatus();
            }
        }

        $sync->setEntityId($entityId);
        $sync->setEntityType(SyncInterface::TYPE_ORDER);
        $sync->setStoreId($storeId);
        $sync->setStatus($statusSync);
        $this->syncResource->save($sync);
    }
}
