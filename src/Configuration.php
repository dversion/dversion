<?php

declare(strict_types=1);

namespace Dversion;

/**
 * The project configuration class.
 */
final class Configuration
{
    private string $versionTableName = 'version_history';

    private string $sqlDirectory = 'sql';

    private bool $devMode = false;

    public function __construct(
        private readonly Driver $driver,
    ) {
    }

    public static function create(Driver $driver) : Configuration
    {
        return new Configuration($driver);
    }

    public function setVersionTableName(string $name) : Configuration
    {
        $this->versionTableName = $name;

        return $this;
    }

    public function setSqlDirectory(string $directory) : Configuration
    {
        $this->sqlDirectory = $directory;

        return $this;
    }

    public function setDevMode(bool $devMode) : Configuration
    {
        $this->devMode = $devMode;

        return $this;
    }

    public function getDriver() : Driver
    {
        return $this->driver;
    }

    public function getVersionTableName() : string
    {
        return $this->versionTableName;
    }

    public function getSqlDirectory() : string
    {
        return $this->sqlDirectory;
    }

    public function isDevMode() : bool
    {
        return $this->devMode;
    }
}
