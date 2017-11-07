<?php

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->addArgument('version', InputArgument::OPTIONAL, 'The version the legacy database corresponds to');
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getArgument('version');

        if ($version === null) {
            $version = 0;
        } else {
            $version = (int) $version;
        }

        $this->getController($output)->init($version);
    }
}
