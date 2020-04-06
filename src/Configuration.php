<?php

namespace Dversion;

/**
 * The project configuration class.
 *
 * @internal
 */
class Configuration
{
    /**
     * @var \Dversion\Driver
     */
    private $driver;

    /**
     * @var string
     */
    private $versionTableName = 'version_history';

    /**
     * @var string
     */
    private $sqlDirectory = 'sql';

    /**
     * @var bool
     */
    private $devMode = false;

    /**
     * @param \Dversion\Driver $driver
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param \Dversion\Driver $driver
     *
     * @return \Dversion\Configuration
     */
    public static function create(Driver $driver) : Configuration
    {
        return new Configuration($driver);
    }

    /**
     * @param string $name
     *
     * @return \Dversion\Configuration
     */
    public function setVersionTableName(string $name) : Configuration
    {
        $this->versionTableName = $name;

        return $this;
    }

    /**
     * @param string $directory
     *
     * @return \Dversion\Configuration
     */
    public function setSqlDirectory(string $directory) : Configuration
    {
        $this->sqlDirectory = $directory;

        return $this;
    }

    /**
     * @param bool $devMode
     *
     * @return \Dversion\Configuration
     */
    public function setDevMode(bool $devMode) : Configuration
    {
        $this->devMode = $devMode;

        return $this;
    }

    /**
     * @return \Dversion\Driver
     */
    public function getDriver() : Driver
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getVersionTableName() : string
    {
        return $this->versionTableName;
    }

    /**
     * @return string
     */
    public function getSqlDirectory() : string
    {
        return $this->sqlDirectory;
    }

    /**
     * @return bool
     */
    public function isDevMode() : bool
    {
        return $this->devMode;
    }
}
