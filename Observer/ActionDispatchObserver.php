<?php

namespace Maatoo\Maatoo\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class ActionDispatchObserver
 * @package Maatoo\Maatoo\Observer
 */
class ActionDispatchObserver implements ObserverInterface
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $session;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Serialize
     */
    private $serialize;

    /**
     * @var \Maatoo\Maatoo\Logger\Logger
     */
    private $logger;

    /**
     * ActionDispatchObserver constructor.
     * @param \Magento\Customer\Model\Session $session
     * @param \Magento\Framework\Serialize\Serializer\Serialize $serialize
     */
    public function __construct(
        \Magento\Customer\Model\Session                   $session,
        \Magento\Framework\Serialize\Serializer\Serialize $serialize,
        \Maatoo\Maatoo\Logger\Logger                      $logger
    )
    {
        $this->session = $session;
        $this->serialize = $serialize;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Framework\App\ActionInterface $action */
        $action = $observer->getEvent()->getData('controller_action');

        /** @var \Magento\Framework\App\RequestInterface $request */
        $request = $observer->getEvent()->getData('request');
        if (!empty($request->getParam('ct'))) {
            $ctDecode = base64_decode(urldecode($request->getParam('ct')));
            if ($ctDecode != false) {
                $ct = unserialize($ctDecode);
                if (isset($ct['channel'])) {
                    $this->session->setConversionData($this->serialize->serialize($ct));
                }
            }
        }
    }
}
