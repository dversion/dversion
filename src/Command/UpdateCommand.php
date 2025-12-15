<?php

declare(strict_types=1);

namespace Dversion\Command;

use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Updates the database.
 *
 * @internal
 */
final class UpdateCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
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

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $test = $input->getOption('test');

        return $this->getController($output)->update($test);
    }
}
