<?php

namespace Dversion\Command;

use Dversion\Configuration;
use Dversion\Driver;

use Symfony\Component\Console\Command\Command;

/**
 * Base class for all commands.
 */
abstract class AbstractCommand extends Command
{
    /**
     * @var \Dversion\Configuration
     */
    protected $configuration;

    /**
     * Class constructor.
     *
     * @param \Dversion\Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        parent::__construct();

        $this->configuration = $configuration;
    }
}
