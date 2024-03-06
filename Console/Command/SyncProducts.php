<?php

namespace Maatoo\Maatoo\Console\Command;

use Maatoo\Maatoo\Service\ProductSyncService;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncProducts
 *
 * @package Maatoo\Maatoo\Console\Command
 */
class SyncProducts extends Command
{
    use LockableTrait;

    private ProductSyncService $sync;
    private State $state;

    /**
     * SyncProducts constructor.
     */
    public function __construct(
        ProductSyncService $sync,
        State $state,
        string $name = null
    ) {
        $this->sync = $sync;
        $this->state = $state;

        parent::__construct($name);
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('maatoo:sync:products');
        $this->setDescription(__('Maatoo synchronization')->render());
        $this->setDefinition([]);

        parent::configure();
    }

    /**
     * Executes SyncProducts
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
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
     * The function generates and synchronizes products using the Maatoo platform
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
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
