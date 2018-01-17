<?php

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Resets the database.
 */
class ResetCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
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

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->getController($output)->reset(
            $input->getOption('resume'),
            $input->getOption('test'),
            $input->getOption('to-version')
        );
    }
}
