<?php

declare(strict_types=1);

namespace Dversion\Command;

use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cleans up stale temporary databases.
 *
 * @internal
 */
final class CleanupCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('cleanup')
            ->setDescription('Clean up the stale temporary databases')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->getController($output)->cleanup();
    }
}
