<?php

declare(strict_types=1);

namespace Dversion\Command;

use Override;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Initializes the versioning table on a legacy database.
 *
 * @internal
 */
final class InitCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize the versioning table on a legacy database')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version the legacy database corresponds to');
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getArgument('version');

        if ($version === null) {
            $version = 0;
        } else {
            $version = (int) $version;
        }

        return $this->getController($output)->init($version);
    }
}
