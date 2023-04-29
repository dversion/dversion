<?php

declare(strict_types=1);

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Resets the database.
 *
 * @internal
 */
class ResetCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('reset')
            ->setDescription('Reset the database and update it to the latest version')
            ->addOption(
                'resume',
                null,
                InputOption::VALUE_NONE,
                'Resume from the latest resume point'
            )
            ->addOption(
                'test',
                null,
                InputOption::VALUE_NONE,
                'Test all the patches against a blank database'
            )
            ->addOption(
                'to-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the version to reset to'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resume = $input->getOption('resume');
        $test = $input->getOption('test');

        $toVersion = $input->getOption('to-version');

        if ($toVersion !== null) {
            $toVersion = (int) $toVersion;
        }

        return $this->getController($output)->reset($resume, $test, $toVersion);
    }
}
