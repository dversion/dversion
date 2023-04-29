<?php

declare(strict_types=1);

namespace Dversion\Command;

use Dversion\Configuration;
use Dversion\Controller;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for all commands.
 *
 * @internal
 */
abstract class AbstractCommand extends Command
{
    private Configuration $configuration;

    final public function __construct(Configuration $configuration)
    {
        parent::__construct();

        $this->configuration = $configuration;
    }

    final protected function getController(OutputInterface $output) : Controller
    {
        return new Controller($this->configuration, $output);
    }
}
