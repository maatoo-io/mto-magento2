<?php

declare(strict_types=1);

namespace Maatoo\Maatoo\Test\Unit\Observer;

use Maatoo\Maatoo\Api\Data\SyncInterface;
use Maatoo\Maatoo\Model\ResourceModel\Sync as SyncResource;
use Maatoo\Maatoo\Model\StoreConfigManager;
use Maatoo\Maatoo\Model\Sync;
use Maatoo\Maatoo\Model\SyncFactory;
use Maatoo\Maatoo\Observer\OrderSaveObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderSaveObserverTest extends TestCase
{
    /**
     * @var SyncFactory|MockObject
     */
    private $syncFactory;

    /**
     * @var SyncResource|MockObject
     */
    private $syncResource;

    /**
     * @var StoreConfigManager|MockObject
     */
    private $storeManager;

    /**
     * @var OrderSaveObserver
     */
    private $observer;

    protected function setUp(): void
    {
        $this->syncFactory = $this->createMock(SyncFactory::class);
        $this->syncResource = $this->createMock(SyncResource::class);
        $this->storeManager = $this->createMock(StoreConfigManager::class);
        $this->observer = new OrderSaveObserver(
            $this->syncFactory,
            $this->syncResource,
            $this->storeManager
        );
    }

    public function testUpdatesOnlyTheOrdersStore(): void
    {
        $order = $this->createOrder(101, 3);
        $sync = $this->getMockBuilder(Sync::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $sync->setData('sync_id', 55);
        $sync->setStatus(SyncInterface::STATUS_SYNCHRONIZED);

        $this->storeManager->expects($this->once())
            ->method('getStoreIds')
            ->willReturn([2, 3, 4]);
        $this->syncFactory->expects($this->once())
            ->method('create')
            ->willReturn($sync);
        $this->syncResource->expects($this->once())
            ->method('loadByType')
            ->with($sync, 101, SyncInterface::TYPE_ORDER, 3);
        $this->syncResource->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Sync $savedSync): bool {
                return $savedSync->getEntityId() === 101
                    && $savedSync->getEntityType() === SyncInterface::TYPE_ORDER
                    && $savedSync->getStoreId() === 3
                    && $savedSync->getStatus() === SyncInterface::STATUS_UPDATED;
            }));

        $this->observer->execute($this->createEventObserver($order));
    }

    public function testIgnoresOrdersFromUnconfiguredStores(): void
    {
        $order = $this->createOrder(101, 9);

        $this->storeManager->expects($this->once())
            ->method('getStoreIds')
            ->willReturn([2, 3, 4]);
        $this->syncFactory->expects($this->never())->method('create');
        $this->syncResource->expects($this->never())->method('loadByType');
        $this->syncResource->expects($this->never())->method('save');

        $this->observer->execute($this->createEventObserver($order));
    }

    private function createOrder(int $quoteId, int $storeId): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getQuoteId')->willReturn($quoteId);
        $order->method('getStoreId')->willReturn($storeId);

        return $order;
    }

    private function createEventObserver(Order $order): Observer
    {
        return new Observer([
            'event' => new Event(['data_object' => $order]),
        ]);
    }
}
