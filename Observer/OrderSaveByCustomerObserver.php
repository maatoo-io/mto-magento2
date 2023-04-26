<?php

namespace Maatoo\Maatoo\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class OrderSaveObserver
 * @package Maatoo\Maatoo\Observer
 */
class OrderSaveByCustomerObserver implements ObserverInterface
{
    /**
     * @var \Maatoo\Maatoo\Model\ConversionFactory
     */
    private $conversionFactory;
    /**
     * @var \Maatoo\Maatoo\Model\ResourceModel\Conversion
     */
    private $conversionResource;
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $session;
    /**
     * @var \Maatoo\Maatoo\Logger\Logger
     */
    private $logger;

    /**
     * OrderSaveObserver constructor.
     * @param \Maatoo\Maatoo\Model\ConversionFactory $conversionFactory
     * @param \Maatoo\Maatoo\Model\ResourceModel\Conversion $conversionResource
     * @param \Magento\Customer\Model\Session $session
     * @param \Maatoo\Maatoo\Logger\Logger $logger
     */
    public function __construct(
        \Maatoo\Maatoo\Model\ConversionFactory $conversionFactory,
        \Maatoo\Maatoo\Model\ResourceModel\Conversion $conversionResource,
        \Magento\Customer\Model\Session $session,
        \Maatoo\Maatoo\Logger\Logger $logger
    )
    {
        $this->conversionFactory = $conversionFactory;
        $this->conversionResource = $conversionResource;
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $observer->getEvent()->getData('order');
        if (!empty($order->getId())) {
            if(!empty($this->session->getConversionData())) {
                $conversion = $this->conversionFactory->create();
                $conversion->setOrderId($order->getId());
                $conversion->setValue($this->session->getConversionData());
                $this->conversionResource->save($conversion);
            }
        }
    }
}
