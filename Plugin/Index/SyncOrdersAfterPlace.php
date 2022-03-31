<?php

namespace Maatoo\Maatoo\Plugin\Index;

use Maatoo\Maatoo\Adapter\AdapterInterface;
use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Model\Config\Config;
use Maatoo\Maatoo\Model\ConversionFactory;
use Maatoo\Maatoo\Model\ResourceModel\Conversion;
use Maatoo\Maatoo\Model\Sync;
use Maatoo\Maatoo\Model\Synchronization\Order;
use Maatoo\Maatoo\Model\SyncRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SyncOrdersAfterPlace
{

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var SyncRepository
     */
    private SyncRepository $syncRepository;

    /**
     * @var Conversion
     */
    private Conversion $conversionResource;

    /**
     * @var ConversionFactory
     */
    private ConversionFactory $conversionFactory;

    /**
     * @var Serialize
     */
    private Serialize $serialize;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $adapter;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * Construct
     *
     * @param CartRepositoryInterface $cartRepository
     * @param StoreManagerInterface $storeManager
     * @param SyncRepository $syncRepository
     * @param Conversion $conversionResource
     * @param ConversionFactory $conversionFactory
     * @param Serialize $serialize
     * @param Config $config
     * @param AdapterInterface $adapter
     * @param LoggerInterface $logger
     * @param Order $order
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        StoreManagerInterface   $storeManager,
        SyncRepository          $syncRepository,
        Conversion              $conversionResource,
        ConversionFactory       $conversionFactory,
        Serialize               $serialize,
        Config                  $config,
        AdapterInterface        $adapter,
        LoggerInterface         $logger,
        Order                   $order
    )
    {
        $this->cartRepository = $cartRepository;
        $this->storeManager = $storeManager;
        $this->syncRepository = $syncRepository;
        $this->conversionResource = $conversionResource;
        $this->conversionFactory = $conversionFactory;
        $this->serialize = $serialize;
        $this->config = $config;
        $this->adapter = $adapter;
        $this->logger = $logger;
        $this->order = $order;
    }

    /**
     * Syncs placed order with maatoo
     *
     * @param $subject
     * @param OrderInterface $order
     *
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function afterPlace($subject, OrderInterface $order): OrderInterface
    {
        if (!$this->config->isAllowedBirthdayInCheckout()) {
            return $order;
        }

        $store = $this->storeManager->getStore($order->getStoreId());

        $quoteId = $order->getQuoteId();
        $quote = $this->cartRepository->get($quoteId);

        $sync = $this->syncRepository->getByParam([
            'entity_id' => $quote->getId(),
            'entity_type' => SyncInterface::TYPE_ORDER,
            'store_id' => $store->getId(),
        ]);

        if ($sync->getData('status') == SyncInterface::STATUS_SYNCHRONIZED) {
            return $order;
        }

        $parameters = $this->order->getParameters($quote);

        if (empty($parameters)) {
            return $order;
        }

        $conversion = $this->conversionFactory->create();
        $this->conversionResource->load($conversion, $quote->getId(), 'order_id');
        if (!empty($conversion->getValue())) {
            $conversionArray = $this->serialize->unserialize($conversion->getValue());
            if (isset($conversionArray['source']) && isset($conversionArray['lead'])) {
                $parameters['conversion']['type'] = $conversionArray['source'][0];
                $parameters['conversion']['id'] = $conversionArray['source'][1];
            }
        }

        $result = [];

        if (empty($sync->getData('status')) || $sync->getData('status') == SyncInterface::STATUS_EMPTY) {
            $result = $this->adapter->makeRequest('orders/new', $parameters, 'POST');
        } elseif ($sync->getData('status') == SyncInterface::STATUS_UPDATED) {
            $result = $this->adapter->makeRequest('orders/' . $sync->getData('maatoo_id') . '/edit', $parameters, 'PATCH');
        } elseif ($sync->getData('status') == SyncInterface::STATUS_DELETED) {
            $result = $this->adapter->makeRequest('orders/' . $sync->getData('maatoo_id') . '/delete', [], 'DELETE');
        }


        if (isset($result['order']['id'])) {
            // Update contacts
            $lead = $this->order->getLead($quote->getId());
            if (!empty($lead->getId()) && !empty($parameters['email'])) {
                $leadId = $lead->getLeadId();
                $data = [
                    'firstname' => $parameters['firstName'] ?? '',
                    'lastname' => $parameters['lastName'] ?? '',
                    'email' => $parameters['email'] ?? '',
                    'birthday' => $parameters['birthday']
                ];
                if (!empty($lead->getSubscribe())) {
                    $data['tags'] = $this->storeManager->getTags($store);
                }
                $this->adapter->makeRequest('contacts/' . $leadId . '/edit', $data, 'PATCH');
            }

            // Update sync table in store
            $param = [
                'entity_id' => $quote->getId(),
                'entity_type' => SyncInterface::TYPE_ORDER
            ];
            /** @var Sync $sync */
            $sync = $this->syncRepository->getByParam($param);
            $sync->setStatus(SyncInterface::STATUS_SYNCHRONIZED);
            $sync->setMaatooId($result['order']['id']);
            $sync->setEntityId($quote->getId());
            $sync->setEntityType(SyncInterface::TYPE_ORDER);
            $sync->setStoreId($quote->getStoreId());
            $this->syncRepository->save($sync);
        }

        return $order;
    }
}