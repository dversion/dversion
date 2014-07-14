<?php

namespace Dversion\Command;

use Dversion\Controller;
use Dversion\Driver;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to show the status of the database.
 */
class StatusCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('status')
            ->setDescription('Shows the sync status of the database with the patch files')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $controller = new Controller($this->configuration, $output);

        return $controller->status();
    }
}
