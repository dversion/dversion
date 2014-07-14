<?php

namespace Dversion;

/**
 * The project configuration class.
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
    public static function create(Driver $driver)
    {
        return new Configuration($driver);
    }

    /**
     * @param string $name
     *
     * @return \Dversion\Configuration
     */
    public function setVersionTableName($name)
    {
        $this->versionTableName = $name;

        return $this;
    }

    /**
     * @param string $directory
     *
     * @return \Dversion\Configuration
     */
    public function setSqlDirectory($directory)
    {
        $this->sqlDirectory = $directory;

        return $this;
    }

    /**
     * @return \Dversion\Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getVersionTableName()
    {
        return $this->versionTableName;
    }

    /**
     * @return string
     */
    public function getSqlDirectory()
    {
        return $this->sqlDirectory;
    }
}
