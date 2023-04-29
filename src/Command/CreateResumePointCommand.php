<?php

declare(strict_types=1);

namespace Dversion\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a resume point.
 *
 * @internal
 */
class CreateResumePointCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('create-resume-point')
            ->setDescription('Create a resume point to speed up database reset')
            ->addOption(
                'resume',
                null,
                InputOption::VALUE_NONE,
                'Resume from the latest resume point'
            )
            ->addOption(
                'at-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the resume point version to create (defaults to latest version)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resume = (bool) $input->getOption('resume');

        $atVersion = $input->getOption('at-version');

        if ($atVersion !== null) {
            $atVersion = (int) $atVersion;
        }

        return $this->getController($output)->createResumePoint($resume, $atVersion);
    }
}
