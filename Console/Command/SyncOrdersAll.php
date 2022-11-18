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
use Maatoo\Maatoo\Model\Synchronization\OrderAll;

class SyncOrdersAll extends Command
{
    use LockableTrait;

    /** Command name */
    const NAME = 'maatoo:sync:orders:all';
    /**
     * @var OrderAll
     */
    private $sync;
    /**
     * @var State
     */
    private $state;

    public function __construct(
        OrderAll $sync,
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
        $this->setName(self::NAME)
            ->setDescription(__('Maatoo synchronization'))
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
            Area::AREA_ADMINHTML,
            [$this, 'generate'],
            [$input, $output]
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function generate(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Maatoo synchronization all orders started.</info>');

        $this->sync->sync(
            function($message) use($output) {
                $output->writeln('<info>' . $message . '</info>');
            }
        );

        $output->writeln(PHP_EOL);
        $output->writeln('<info>Maatoo synchronization all orders finished.</info>');

        return Cli::RETURN_SUCCESS;
    }
}
