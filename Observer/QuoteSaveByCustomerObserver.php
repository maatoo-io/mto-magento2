<?php

namespace Maatoo\Maatoo\Observer;

use Magento\Framework\Event\ObserverInterface;

class QuoteSaveByCustomerObserver implements ObserverInterface
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
     * @var \Maatoo\Maatoo\Model\OrderLeadFactory
     */
    private $orderLeadFactory;

    /**
     * @var \Maatoo\Maatoo\Model\ResourceModel\OrderLead
     */
    private $orderLeadResource;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $session;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    private $cookieManager;

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
        \Maatoo\Maatoo\Model\OrderLeadFactory $orderLeadFactory,
        \Maatoo\Maatoo\Model\ResourceModel\OrderLead $orderLeadResource,
        \Magento\Customer\Model\Session $session,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Maatoo\Maatoo\Logger\Logger $logger
    )
    {
        $this->conversionFactory = $conversionFactory;
        $this->conversionResource = $conversionResource;
        $this->orderLeadFactory = $orderLeadFactory;
        $this->orderLeadResource = $orderLeadResource;
        $this->session = $session;
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getData('data_object');
        if (!empty($quote->getId())) {
            $this->saveConversion($quote);
            $this->saveOrderLead($quote);
        }
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function saveConversion(\Magento\Quote\Model\Quote $quote)
    {
        if(!empty($this->session->getConversionData())) {
            $conversion = $this->conversionFactory->create();
            $this->conversionResource->load($conversion, $quote->getId(), 'order_id');
            if(empty($conversion->getData('conversion_id'))) {
                $conversion->setOrderId($quote->getId());
                $conversion->setValue($this->session->getConversionData());
                $this->conversionResource->save($conversion);
            }
        }
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function saveOrderLead(\Magento\Quote\Model\Quote $quote)
    {
        if(!empty($this->cookieManager->getCookie('mtc_id'))) {
            $mtcId = $this->cookieManager->getCookie('mtc_id');
            $orderLead = $this->orderLeadFactory->create();
            $this->orderLeadResource->load($orderLead, $quote->getId(), 'order_id');
            $orderLead->setOrderId($quote->getId());
            $orderLead->setLeadId($mtcId);
            $this->orderLeadResource->save($orderLead);
        }
    }
}
