<?php
declare(strict_types=1);

namespace Maatoo\Maatoo\Console\Command;

use Maatoo\Maatoo\Service\CategorySyncService;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCategories extends Command
{
    use LockableTrait;

    private $sync;

    /**
     * SyncCategories constructor.
     */
    public function __construct(
        CategorySyncService $sync,
        string              $name = null
    ) {
        $this->sync = $sync;

        parent::__construct($name);
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('maatoo:sync:categories')
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

        $output->writeln('<info>Maatoo synchronization categories started.</info>');
        $this->sync->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );
        $output->writeln('<info>Maatoo synchronization categories finished.</info>');

        return Cli::RETURN_SUCCESS;
    }
}
