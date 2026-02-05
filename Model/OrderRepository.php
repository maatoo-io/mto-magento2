<?php

namespace Maatoo\Maatoo\Model;

class OrderRepository
{
    protected $orderFactory;

    protected $orderResourceModel;

    public function __construct
    (
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\ResourceModel\Order $orderResourceModel
    )
    {
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
    }

    public function getByIncrementId(?string $incrementId = null)
    {
        if(empty($incrementId)) {
            return null;
        }

        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order,$incrementId,'increment_id');
        return $order;
    }
}
