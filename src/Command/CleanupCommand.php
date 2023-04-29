<?php

declare(strict_types=1);

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cleans up stale temporary databases.
 *
 * @internal
 */
class CleanupCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('cleanup')
            ->setDescription('Clean up the stale temporary databases')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->getController($output)->cleanup();
    }
}
