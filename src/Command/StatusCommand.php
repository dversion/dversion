<?php

declare(strict_types=1);

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows the status of the database.
 *
 * @internal
 */
class StatusCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Show the sync status of the database with the patch files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->getController($output)->status();
    }
}
