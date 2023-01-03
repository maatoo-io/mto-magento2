<?php

namespace Maatoo\Maatoo\Observer;

use Magento\Framework\Event\ObserverInterface;
use Maatoo\Maatoo\Api\Data\SyncInterface;

/**
 * Class EntityDeleteObserver
 * @package Maatoo\Maatoo\Observer
 */
class EntityDeleteObserver implements ObserverInterface
{
    /**
     * @var \Maatoo\Maatoo\Model\SyncFactory
     */
    private $syncFactory;

    /**
     * @var \Maatoo\Maatoo\Model\ResourceModel\Sync
     */
    private $syncResource;

    /**
     * @var
     */
    private $entityType;

    /**
     * @var \Maatoo\Maatoo\Model\StoreConfigManager
     */
    private $storeManager;

    /**
     * EntitySaveObserver constructor.
     * @param \Maatoo\Maatoo\Model\SyncFactory $syncFactory
     * @param \Maatoo\Maatoo\Model\ResourceModel\Sync $syncResource
     * @param $entityType
     */
    public function __construct(
        \Maatoo\Maatoo\Model\SyncFactory $syncFactory,
        \Maatoo\Maatoo\Model\ResourceModel\Sync $syncResource,
        \Maatoo\Maatoo\Model\StoreConfigManager $storeManager,
        $entityType
    )
    {
        $this->syncFactory = $syncFactory;
        $this->syncResource = $syncResource;
        $this->entityType = $entityType;
        $this->storeManager = $storeManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $entity = $observer->getEvent()->getData('data_object');
        /*$extensionAttribute = $entity->getExtensionAttributes();
        if (!$extensionAttribute && !$extensionAttribute->getMaatooStatus) {
            return;
        }*/

        $entityId = (int)$entity->getId();

        foreach ($this->storeManager->getStores(false) as $store)
        {
            /** @var \Maatoo\Maatoo\Model\Sync $sync */
            $sync = $this->syncFactory->create();
            $this->syncResource->loadByType($sync, $entityId, $this->entityType, $store->getId());

            if ($sync->getSyncId()) {
                if ($sync->getStatus() == 0) {
                    $this->syncResource->delete($sync);
                } else {
                    $sync->setEntityId($entityId);
                    $sync->setEntityType($this->entityType);
                    $sync->setStatus(SyncInterface::STATUS_DELETED);
                    $this->syncResource->save($sync);
                }
            }
        }
    }
}
