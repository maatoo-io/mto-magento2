<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Console\Command;

use Maatoo\Maatoo\Model\Synchronization\OrderAll;
use Maatoo\Maatoo\Model\Synchronization\OrderLinesAll;
use Maatoo\Maatoo\Service\CategorySyncService;
use Maatoo\Maatoo\Service\ProductSyncService;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Maatoo\Maatoo\Model\Synchronization\Store;
use Maatoo\Maatoo\Model\Synchronization\Order;
use Maatoo\Maatoo\Model\Synchronization\OrderLines;

class MaatooSynchronization extends Command
{
    use LockableTrait;

    /**
     * @var Store
     */
    private $store;
    /**
     * @var CategorySyncService
     */
    private $category;
    /**
     * @var ProductSyncService
     */
    private $product;
    /**
     * @var Order
     */
    private $order;

    /**
     * @var OrderAll
     */
    private $orderAll;

    /**
     * @var OrderLines
     */
    private $orderLines;

    /**
     * @var OrderLinesAll
     */
    private $orderLinesAll;

    /**
     * @var State
     */
    private $state;

    public function __construct(
        Store $store,
        CategorySyncService $category,
        ProductSyncService $product,
        Order $order,
        OrderAll $orderAll,
        OrderLines $orderLines,
        OrderLinesAll $orderLinesAll,
        State $state,
        string $name = null
    )
    {
        parent::__construct($name);
        $this->store = $store;
        $this->category = $category;
        $this->product = $product;
        $this->order = $order;
        $this->orderAll = $orderAll;
        $this->orderLines = $orderLines;
        $this->orderLinesAll = $orderLinesAll;
        $this->state = $state;
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('maatoo:sync:all')
            ->setDescription(__('Maatoo synchronization')->render())
            ->setDefinition([]);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Cli::RETURN_SUCCESS;
        }

        $this->state->emulateAreaCode(
            Area::AREA_FRONTEND,
            [$this, 'generate'],
            [$input, $output]
        );

        return Cli::RETURN_SUCCESS;
    }

    public function generate(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Maatoo synchronization stores started.</info>');
        $this->store->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln(PHP_EOL);
        $output->writeln('<info>Maatoo synchronization stores finished.</info>');
        sleep(10);
        $output->writeln('<info>Maatoo synchronization categories started.</info>');
        $this->category->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln('<info>Maatoo synchronization categories finished.</info>');
        sleep(10);
        $output->writeln('<info>Maatoo synchronization products started.</info>');
        $this->product->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln('<info>Maatoo synchronization products finished.</info>');
        sleep(10);
        $output->writeln('<info>Maatoo synchronization orders started.</info>');
        $this->order->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln(PHP_EOL);
        $output->writeln('<info>Maatoo synchronization orders finished.</info>');
        sleep(10);
        $output->writeln('<info>Maatoo synchronization order lines started.</info>');
        $this->orderLines->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln('<info>Maatoo synchronization order lines finished.</info>');
        sleep(10);
        $output->writeln('<info>Maatoo synchronization orders started.</info>');
        $this->orderAll->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln(PHP_EOL);
        $output->writeln('<info>Maatoo synchronization orders finished.</info>');
        sleep(10);
        $output->writeln(PHP_EOL);
        $output->writeln('<info>Maatoo synchronization all order lines started.</info>');
        $this->orderLinesAll->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln(PHP_EOL);
        $output->writeln('<info>Maatoo synchronization all order lines finished.</info>');

        return Cli::RETURN_SUCCESS;
    }
}
