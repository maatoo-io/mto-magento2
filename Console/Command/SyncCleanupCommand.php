<?php

namespace Maatoo\Maatoo\Console\Command;

use Maatoo\Maatoo\Service\CleanUpSyncTableService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncCleanupCommand
 */
class SyncCleanupCommand extends Command
{
    /**
     * @var CleanUpSyncTableService
     */
    private $cleanUpService;

    /**
     * @param CleanUpSyncTableService $cleanUpService
     * @param string|null $name
     */
    public function __construct(
        CleanUpSyncTableService $cleanUpService,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->cleanUpService = $cleanUpService;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('maatoo:sync:cleanup')
            ->setDescription('Cleans up the maatoo_sync table by removing duplicates, orphans, and old records.')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Retention period in days (overrides system config)'
            );

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting maatoo_sync table cleanup...</info>');

        try {
            $days = $input->getOption('days');

            if ($days !== null) {
                $days = (int)$days;
            }

            $summary = $this->cleanUpService->execute($days);

            $table = new Table($output);
            $table->setHeaders(['Step', 'Records Deleted'])
                ->setRows([
                    ['Step A: Duplicate Removal', $summary['duplicates']],
                    ['Step B: Orphaned Store Cleanup', $summary['orphans']],
                    ['Step C: General Retention Policy Cleanup', $summary['retention']],
                    ['Step D: Disabled Products Cleanup', $summary['products_cleaned']],
                    ['Step E: Inactive Customers Cleanup', $summary['customers_cleaned']],
                ]);

            $table->render();

            $output->writeln('<info>Cleanup completed successfully.</info>');
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Cleanup failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
