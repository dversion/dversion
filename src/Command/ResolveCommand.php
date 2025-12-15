<?php

declare(strict_types=1);

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Resolves a failed update.
 *
 * @internal
 */
final class ResolveCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('resolve')
            ->setDescription('Resolve a failed update')
            ->addArgument('direction', InputArgument::REQUIRED, 'forward or backward')
            ->setHelp(
                'When a database update could not be completed due to an error, dversion cannot ' .
                'handle the situation automatically.' . PHP_EOL .
                PHP_EOL .
                'You must check and fix what went wrong, and manually update your database to bring it to a ' .
                'known state. Then you must tell dversion how you handled the issue:' . PHP_EOL .
                '- if you manually reverted the changes partially applied by the patch, run:' . PHP_EOL .
                '  dversion resolve backward' . PHP_EOL .
                '- if you manually applied the rest of the patch, run:' . PHP_EOL .
                '  dversion resolve forward' . PHP_EOL .
                PHP_EOL .
                'This command really just sets the current database version.' . PHP_EOL .
                'For example, if update to version 7 failed, resolve backward will set the current version to 6, ' .
                'while resolve forward will set the current version to 7.'
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $direction = $input->getArgument('direction');

        return $this->getController($output)->resolve($direction);
    }
}
