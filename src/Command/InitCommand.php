<?php

namespace Dversion\Command;

use Dversion\Driver;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Initializes the versioning table on a legacy database.
 */
class InitCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initialize the versioning table on a legacy database')
            ->addArgument('version', InputArgument::REQUIRED, 'The version the legacy database corresponds to');
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->getController($output)->init(
            (int) $input->getOption('version')
        );
    }
}
