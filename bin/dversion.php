<?php

use Dversion\Driver;
use Dversion\Command;

use Symfony\Component\Console\Application;

set_time_limit(0);

if (file_exists($autoloader = __DIR__ . '/../../../autoload.php')) {
    // Composer autoloader when dversion is installed as a dependency.
    require $autoloader;
}

/** @var Dversion\Configuration $configuration */
$configuration = require 'dversion.php';

$application = new Application();

$application->add(new Command\StatusCommand($configuration));
$application->add(new Command\UpdateCommand($configuration));
$application->add(new Command\ResetCommand($configuration));
$application->add(new Command\CreateResumePointCommand($configuration));

$application->run();
