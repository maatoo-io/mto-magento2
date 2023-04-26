<?php

namespace Maatoo\Maatoo\Observer;

use Magento\Framework\Event\ObserverInterface;

class CheckoutSubmitAllObserver implements ObserverInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Maatoo\Maatoo\Logger\Logger
     */
    private $logger;

    /**
     * @var \Magento\Checkout\Api\Data\ShippingInformationInterface
     */
    private $shippingInformation;

    /**
     * @var \Maatoo\Maatoo\Adapter\Curl
     */
    private $curl;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var \Maatoo\Maatoo\Model\OrderLeadFactory
     */
    private $orderLeadFactory;

    /**
     * @var \Maatoo\Maatoo\Model\ResourceModel\OrderLead
     */
    private $orderLeadResource;


    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $shippingInformation,
        \Maatoo\Maatoo\Adapter\Curl $curl,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Maatoo\Maatoo\Model\OrderLeadFactory $orderLeadFactory,
        \Maatoo\Maatoo\Model\ResourceModel\OrderLead $orderLeadResource,
        \Maatoo\Maatoo\Logger\Logger $logger
    ) {
        $this->storeManager = $storeManager;
        $this->shippingInformation = $shippingInformation;
        $this->curl = $curl;
        $this->cookieManager = $cookieManager;
        $this->orderLeadFactory = $orderLeadFactory;
        $this->orderLeadResource = $orderLeadResource;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Quote\Api\Data\CartInterface $quote */
        $quote = $observer->getData('quote');

        if(!empty($quote->getBillingAddress()->getExtensionAttributes()->getMaatooOptIn())) {
            try {
                $orderLead = $this->orderLeadFactory->create();
                $this->orderLeadResource->load($orderLead, $quote->getId(), 'order_id');
                $orderLead->setSubscribe(1);
                $this->orderLeadResource->save($orderLead);
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

    }
}
