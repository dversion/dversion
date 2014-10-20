<?php

namespace Dversion\Command;

use Dversion\Driver;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Updates the database.
 */
class UpdateCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update the database to the latest version')
            ->addOption(
                'test',
                null,
                InputOption::VALUE_NONE,
                'Test the update on a copy of the database'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->getController($output)->update(
            (bool) $input->getOption('test')
        );
    }
}
