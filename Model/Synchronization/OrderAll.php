<?php

namespace Maatoo\Maatoo\Model\Synchronization;

use DateTime;
use Maatoo\Maatoo\Adapter\AdapterInterface;
use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Helper\DataSync;
use Maatoo\Maatoo\Model\StoreConfigManager;
use Maatoo\Maatoo\Model\StoreMap;
use Maatoo\Maatoo\Model\SyncRepository;
use Magento\Framework\UrlInterface;
use Maatoo\Maatoo\Model\OrderStatusMap;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Class OrderAll
 *
 * @package Maatoo\Maatoo\Model\Synchronization
 */
class OrderAll
{

    /**
     * @var StoreConfigManager
     */
    private $storeManager;

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var StoreMap
     */
    private $storeMap;

    /**
     * @var SyncRepository
     */
    private $syncRepository;

    /**
     * @var UrlInterface
     */
    private $urlBilder;

    /**
     * @var CollectionFactory
     */
    private $collectionOrderFactory;

    /**
     * @var DataSync
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        StoreConfigManager $storeManager,
        CollectionFactory $collectionOrderFactory,
        UrlInterface $urlBilder,
        AdapterInterface $adapter,
        StoreMap $storeMap,
        SyncRepository $syncRepository,
        DataSync $helper,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->collectionOrderFactory = $collectionOrderFactory;
        $this->urlBilder = $urlBilder;
        $this->adapter = $adapter;
        $this->storeMap = $storeMap;
        $this->syncRepository = $syncRepository;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @param  \Closure|null  $cl
     */
    public function sync(\Closure $cl = null)
    {
        $this->logger->info("Begin syncing all orders to maatoo.");
        /** @var \Magento\Store\Api\Data\StoreInterface $store */
        foreach ($this->storeManager->getStores() as $store) {
            $collection = $this->collectionOrderFactory->create();
            $collection->addFieldToFilter('store_id', $store->getId());
            $collection->addFieldToFilter('maatoo_sync', SyncInterface::ORDER_STATUS_EMPTY);

            $select = $collection->getSelect();
            $select->limit(1000);

            $maatoSyncInsertData = [];
            $updateOrdersData = [];

            foreach ($collection->getItems() as $order) {
                $quoteId = $order->getQuoteId();

                /** @var \Maatoo\Maatoo\Model\Sync $sync */
                $sync = $this->syncRepository->getByParam([
                    'entity_id' => $quoteId,
                    'entity_type' => SyncInterface::TYPE_ORDER,
                    'store_id' => $store->getId(),
                ]);

                if ($sync->getData('status') == SyncInterface::STATUS_SYNCHRONIZED) {
                    $updateOrdersData[$order->getId()] = SyncInterface::ORDER_STATUS_SYNCHRONIZED;
                    continue;
                }

                $parameters = $this->getParameters($order);

                if (empty($parameters)) {
                    $updateOrdersData[$order->getId()] = SyncInterface::ORDER_STATUS_SYNCHRONIZED;
                    continue;
                }

                $result = [];

                if (empty($sync->getData('status')) || $sync->getData('status') == SyncInterface::STATUS_EMPTY) {
                    $result = $this->adapter->makeRequest('orders/new', $parameters, 'POST');
                    $this->logger->info('Added order #'.$quoteId  . ' to maatoo');
                    if (is_callable($cl)) {
                        $cl('Added order #'.$quoteId  . ' to maatoo');
                    }
                }

                if (isset($result['order']['id'])) {
                    $maatoSyncInsertData[] = [
                        'status' => SyncInterface::STATUS_SYNCHRONIZED,
                        "maatoo_id" => $result['order']['id'],
                        "entity_id" => $quoteId,
                        "entity_type" => SyncInterface::TYPE_ORDER,
                        "store_id" => $order->getStoreId(),
                    ];

                    $updateOrdersData[$order->getId()] = SyncInterface::ORDER_STATUS_SYNCHRONIZED;
                }

                if ($sync->getData('status') == SyncInterface::STATUS_UPDATED) {
                    $updateOrdersData[$order->getId()] = SyncInterface::ORDER_STATUS_SYNCHRONIZED;
                }
            }
            $this->helper->executeUpdateSalesOrderTable($updateOrdersData);
            $this->helper->executeInsertOnDuplicate($maatoSyncInsertData);
        }
        $this->logger->info("Finished syncing all orders to maatoo.");
    }

    /**
     * Set parameters to sync in maatoo system
     *
     * @param  \Magento\Sales\Model\Order  $order
     *
     * @return array|null
     */
    public function getParameters(\Magento\Sales\Model\Order $order)
    {
        $parameters = [];
        $leadId = '';

        if ($order !== null && !empty($order->getId())) {
            $billingAddress = $order->getBillingAddress();
            $birthdayDateTime = $billingAddress ? $billingAddress->getBirthday() : '';
            if ($birthdayDateTime) {
                $birthdayDate = DateTime::createFromFormat('Y-m-d H:i:s', $birthdayDateTime)->format('Y-m-d');
            }

            $parameters = [
                'store' => $this->storeMap->getStoreToMaatoo($order->getStoreId()),
                'externalOrderId' => $order->getIncrementId(),
                'externalDateProcessed' => $order->getCreatedAt(),
                'externalDateUpdated' => $order->getUpdatedAt(),
                'value' => (float)$order->getGrandTotal(),
                'discountValue' => (float)$order->getDiscountAmount(),
                'taxValue' => (float)$order->getTaxAmount(),
                'shippingValue' => (float)$order->getShippingAmount(),
                'url' => $this->urlBilder->getUrl('sales/order/view', ['id' => $order->getId()]),
                'status' => OrderStatusMap::getStatus($order->getStatus()),
                'paymentMethod' => $order->getPayment()->getMethod(),
                'email' => $order->getCustomerEmail() ?: '',
                'firstName' => $order->getCustomerFirstname() ?: '',
                'lastName' => $order->getCustomerLastname() ?: '',
                'lead_id' => $leadId,
                'conversion' => [],
                'birthday' => $birthdayDate ?? ''
            ];

            if ($order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
                $orderCancelDate = null;
                $commentCollection = $order->getStatusHistoryCollection();
                /**
                 * @var $comment \Magento\Sales\Model\Order\Status\History
                 */
                foreach ($commentCollection as $comment) {
                    if ($comment->getStatus() === \Magento\Sales\Model\Order::STATE_CANCELED) {
                        $orderCancelDate = $comment->getCreatedAt();
                    }
                }

                if ($orderCancelDate) {
                    $parameters['externalDateCancelled'] = $orderCancelDate;
                }
            }
        }

        return $parameters;
    }
}
