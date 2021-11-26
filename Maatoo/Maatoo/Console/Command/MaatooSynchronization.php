<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Console\Command;

use Exception;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Maatoo\Maatoo\Model\Synchronization\Store;
use Maatoo\Maatoo\Model\Synchronization\Category;
use Maatoo\Maatoo\Model\Synchronization\Product;
use Maatoo\Maatoo\Model\Synchronization\Order;
use Maatoo\Maatoo\Model\Synchronization\OrderLines;

class MaatooSynchronization extends Command
{
    /**
     * @var Store
     */
    private $store;
    /**
     * @var Category
     */
    private $category;
    /**
     * @var Product
     */
    private $product;
    /**
     * @var Order
     */
    private $order;
    /**
     * @var OrderLines
     */
    private $orderLines;

    public function __construct(
        Store $store,
        Category $category,
        Product $product,
        Order $order,
        OrderLines $orderLines,
        string $name = null
    )
    {
        parent::__construct($name);
        $this->store = $store;
        $this->category = $category;
        $this->product = $product;
        $this->order = $order;
        $this->orderLines = $orderLines;
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('maatoo:sync:all')
            ->setDescription(__('Maatoo synchronization'))
            ->setDefinition([]);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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

        return Cli::RETURN_SUCCESS;
    }
}
