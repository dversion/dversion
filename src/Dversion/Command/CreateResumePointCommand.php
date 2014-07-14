<?php

namespace Dversion\Command;

use Dversion\Controller;
use Dversion\Driver;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to create a resume point.
 */
class CreateResumePointCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
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
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $controller = new Controller($this->configuration, $output);

        return $controller->createResumePoint(
            (bool) $input->getOption('resume')
        );
    }
}
