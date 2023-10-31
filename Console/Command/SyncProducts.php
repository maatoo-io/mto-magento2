<?php

namespace Maatoo\Maatoo\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Maatoo\Maatoo\Model\Synchronization\Product;

/**
 * Class SyncProducts
 * @package Maatoo\Maatoo\Console\Command
 */
class SyncProducts extends Command
{
    use LockableTrait;

    /**
     * @var Product
     */
    private $sync;

    /**
     * @var State
     */
    private $state;

    /**
     * SyncProducts constructor.
     * @param Product $sync
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        Product $sync,
        State $state,
        string $name = null
    )
    {
        $this->sync = $sync;
        $this->state = $state;
        parent::__construct($name);
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('maatoo:sync:products')
            ->setDescription(__('Maatoo synchronization')->render())
            ->setDefinition([]);
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws \Exception
     */
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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function generate(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Maatoo synchronization products started.</info>');
        $this->sync->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln('<info>Maatoo synchronization products finished.</info>');

        return Cli::RETURN_SUCCESS;
    }
}
