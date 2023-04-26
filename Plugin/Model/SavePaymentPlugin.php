<?php

namespace Maatoo\Maatoo\Plugin\Model;

use Maatoo\Maatoo\Model\OrderLeadFactory;
use Maatoo\Maatoo\Model\ResourceModel\OrderLead;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Maatoo\Maatoo\Logger\Logger;

class SavePaymentPlugin
{
    protected $quoteRepository;

    private $orderLeadFactory;

    private $orderLeadResource;

    private $logger;

    /**
     * SavePaymentPlugin constructor
     *
     * @param OrderLeadFactory $orderLeadFactory
     * @param OrderLead $orderLeadResource
     * @param Logger $logger
     */
    public function __construct(
        OrderLeadFactory $orderLeadFactory,
        OrderLead $orderLeadResource,
        Logger $logger
    ) {
        $this->orderLeadFactory = $orderLeadFactory;
        $this->orderLeadResource = $orderLeadResource;
        $this->logger = $logger;
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     *
     * @throws NoSuchEntityException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
                                              $cartId,
        PaymentInterface                      $paymentMethod,
        AddressInterface                      $billingAddress = null
    ) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $quoteRepository = $objectManager->create('Magento\Quote\Api\CartRepositoryInterface');
        $quote = $quoteRepository->getActive($cartId);

        if (!empty($paymentMethod->getExtensionAttributes()->getMaatooOptIn())) {
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
