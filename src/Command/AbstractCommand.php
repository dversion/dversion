<?php

namespace Dversion\Command;

use Dversion\Configuration;
use Dversion\Controller;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for all commands.
 */
abstract class AbstractCommand extends Command
{
    /**
     * @var \Dversion\Configuration
     */
    private $configuration;

    /**
     * Class constructor.
     *
     * @param \Dversion\Configuration $configuration
     */
    final public function __construct(Configuration $configuration)
    {
        parent::__construct();

        $this->configuration = $configuration;
    }

    /**
     * @param OutputInterface $output
     *
     * @return \Dversion\Controller
     */
    final public function getController(OutputInterface $output) : Controller
    {
        return new Controller($this->configuration, $output);
    }
}
