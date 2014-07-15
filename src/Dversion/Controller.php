<?php

namespace Dversion;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Holds the application logic.
 */
class Controller
{
    /**
     * @var \Dversion\Configuration
     */
    private $configuration;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @param Configuration   $configuration
     * @param OutputInterface $output
     */
    public function __construct(Configuration $configuration, OutputInterface $output)
    {
        $this->configuration = $configuration;
        $this->output        = $output;

        $this->setDriverExceptionMode($configuration->getDriver());
    }

    /**
     * @return integer
     */
    public function status()
    {
        $driver = $this->configuration->getDriver();

        $currentVersion = $this->getCurrentDatabaseVersion($driver);
        $latestVersion  = $this->getLatestDatabaseVersion();

        $this->output->writeln('Current database version: ' . $currentVersion);
        $this->output->writeln('Latest database version: ' . $latestVersion);

        $diff = $currentVersion - $latestVersion;

        if ($diff < 0) {
            $this->output->writeln('The database is out of date, you should run dversion update.');
        } elseif ($diff > 0) {
            $this->output->writeln('The database version is greater than the latest revision file.');
            $this->output->writeln('You should probably update your working copy.');
        } else {
            $this->output->writeln('The database is up to date!');
        }

        return 0;
    }

    /**
     * @param boolean $test
     *
     * @return integer
     */
    public function update($test)
    {
        $temporaryDatabaseName = $this->getTemporaryDatabaseName();

        if ($test) {
            $targetDriver = $this->createDatabase($temporaryDatabaseName);
            $this->copyDatabase($targetDriver);
        } else {
            $targetDriver = $this->configuration->getDriver();
        }

        $currentVersion = $this->getCurrentDatabaseVersion($targetDriver);
        $this->output->writeln('Current database version: ' . $currentVersion);

        $latestVersion = $this->getLatestDatabaseVersion();

        if ($currentVersion > $latestVersion) {
            $this->output->writeln('The database version is greater than the latest revision file.');
            $this->output->writeln('You should probably update your working copy.');

            return 1;
        }

        if ($currentVersion == $latestVersion) {
            $this->output->writeln('The database is up to date!');
        } else {
            $this->runUpdates($targetDriver, $currentVersion, $latestVersion);

            $this->output->writeln('Success!');
        }

        if ($test) {
            $this->dropDatabase($temporaryDatabaseName);
        }

        return 0;
    }

    /**
     * @param boolean $resume
     * @param boolean $test
     *
     * @return integer
     */
    public function reset($resume, $test)
    {
        if ($test) {
            $targetDatabaseName = $this->getTemporaryDatabaseName();
        } else {
            $targetDatabaseName = $this->configuration->getDriver()->getDatabaseName();
            $this->dropDatabase($targetDatabaseName);
        }

        $targetDriver = $this->createDatabase($targetDatabaseName);

        if ($resume) {
            $currentVersion = $this->getDumpVersion();
            $this->output->writeln('Resuming at version ' . $currentVersion);
            $sqlFilePath = $this->getSqlDumpPath() . '/' . $currentVersion . '.sql';
            $this->importSqlFile($targetDriver->getPdo(), $sqlFilePath);

            if ($currentVersion != $this->getCurrentDatabaseVersion($targetDriver)) {
                $this->output->writeln('Error resuming version ' . $currentVersion);

                return 1;
            }
        } else {
            $this->output->writeln('Creating the versioning table');
            $targetDriver->createVersionTable($this->configuration->getVersionTableName());
            $currentVersion = 0;
        }

        $latestVersion = $this->getLatestDatabaseVersion();

        $this->runUpdates($targetDriver, $currentVersion, $latestVersion);

        if ($test) {
            $this->dropDatabase($targetDatabaseName);
        }

        $this->output->writeln('Success!');

        return 0;
    }

    /**
     * @param boolean $resume
     *
     * @return integer
     */
    public function createResumePoint($resume)
    {
        $targetDatabaseName = $this->getTemporaryDatabaseName();
        $targetDriver = $this->createDatabase($targetDatabaseName);

        if ($resume) {
            $currentVersion = $this->getDumpVersion();
            $this->output->writeln('Resuming at version ' . $currentVersion);
            $sqlFilePath = $this->getSqlDumpPath() . '/' . $currentVersion . '.sql';
            $this->importSqlFile($targetDriver->getPdo(), $sqlFilePath);

            if ($currentVersion != $this->getCurrentDatabaseVersion($targetDriver)) {
                $this->output->writeln('Error resuming version ' . $currentVersion);

                return 1;
            }
        } else {
            $this->output->writeln('Creating the versioning table');
            $targetDriver->createVersionTable($this->configuration->getVersionTableName());
            $currentVersion = 0;
        }

        $latestVersion = $this->getLatestDatabaseVersion();

        $this->runUpdates($targetDriver, $currentVersion, $latestVersion);
        $this->doCreateResumePoint($targetDriver, $latestVersion);
        $this->dropDatabase($targetDatabaseName);

        $this->output->writeln('Success!');

        return 0;
    }

    /**
     * @param Driver  $targetDriver
     * @param integer $currentVersion
     * @param integer $latestVersion
     *
     * @return void
     */
    private function runUpdates(Driver $targetDriver, $currentVersion, $latestVersion)
    {
        for ($version = $currentVersion + 1; $version <= $latestVersion; $version++) {
            $sqlFilePath = $this->getSqlPath() . '/' . $version . '.sql';

            $this->output->writeln('Upgrading to version ' . $version);
            $this->startUpgrade($targetDriver, $version);
            $this->importSqlFile($targetDriver->getPdo(), $sqlFilePath);
            $this->endUpgrade($targetDriver, $version);
        }
    }

    /**
     * @param string $name The name of the database to create.
     *
     * @return Driver
     */
    private function createDatabase($name)
    {
        $this->output->writeln('Creating database ' . $name);

        $driver = $this->configuration->getDriver()->createDatabase($name);
        $this->setDriverExceptionMode($driver);

        return $driver;
    }

    /**
     * @param string $name The name of the database to drop.
     *
     * @return void
     */
    private function dropDatabase($name)
    {
        $this->output->writeln('Dropping database ' . $name);

        $this->configuration->getDriver()->dropDatabase($name);
    }

    /**
     * @param Driver $targetDriver The target driver.
     *
     * @return void
     */
    private function copyDatabase(Driver $targetDriver)
    {
        $this->doDumpDatabase($this->configuration->getDriver(), function($query) use ($targetDriver) {
            $targetDriver->getPdo()->exec($query);
        });
    }

    /**
     * @param Driver   $sourceDriver The source driver.
     * @param callable $output       A function to call with every SQL statement.
     *
     * @return void
     */
    private function doDumpDatabase(Driver $sourceDriver, $output)
    {
        $dumper = new Dumper($sourceDriver);

        $progress = new ProgressBar($this->output);
        $progress->setMessage('Counting objects');
        $progress->setFormat('%message% [%bar%] %current%');
        $progress->start();

        $objectCount = 0;

        $dumper->countObjects(function($count) use ($progress, & $objectCount) {
            $objectCount += $count;
            $progress->setCurrent($objectCount);
        });

        $progress->finish();
        $this->output->writeln('');

        $progress = new ProgressBar($this->output, $objectCount);
        $progress->setMessage('Copying database');
        $progress->setFormat('%message% [%bar%] %current%/%max% %percent:3s%%');
        $progress->start();

        $dumper->dumpDatabase(function($query) use ($output, $progress) {
            $output($query);
            $progress->advance();
        });

        $progress->finish();
        $this->output->writeln('');
    }

    /**
     * @return integer
     *
     * @throws \RuntimeException
     */
    private function getLatestDatabaseVersion()
    {
        $versions = $this->getSqlFileVersions($this->getSqlPath());
        $latestVersion = end($versions);

        if ($versions !== range(1, $latestVersion)) {
            throw new \RuntimeException('One or more SQL version files are missing');
        }

        return $latestVersion;
    }

    /**
     * @return integer
     *
     * @throws \RuntimeException
     */
    private function getDumpVersion()
    {
        $versions = $this->getSqlFileVersions($this->getSqlDumpPath());

        if (count($versions) == 0) {
            throw new \RuntimeException('Cannot find SQL dump file');
        }

        return reset($versions);
    }

    /**
     * @param string $directory
     *
     * @return array
     */
    private function getSqlFileVersions($directory)
    {
        $files = new \DirectoryIterator($directory);
        $versions = array();

        foreach ($files as $file) {
            /** @var $file \DirectoryIterator */
            if ($file->isFile()) {
                if (preg_match('/^([0-9]+)\.sql$/', $file->getFilename(), $matches)) {
                    $versions[] = (int) $matches[1];
                } else {
                    $this->output->writeln('Skipping file ' . $file->getFilename());
                }
            }
        }

        sort($versions);

        return $versions;
    }

    /**
     * @return string
     */
    private function getSqlPath()
    {
        return $this->configuration->getSqlDirectory();
    }

    /**
     * @return string
     */
    private function getSqlDumpPath()
    {
        return $this->getSqlPath() . '/dump';
    }

    /**
     * @param \PDO   $pdo
     * @param string $file
     *
     * @return void
     */
    private function importSqlFile(\PDO $pdo, $file)
    {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }

    /**
     * @param Driver $driver
     *
     * @return integer
     *
     * @throws \RuntimeException
     */
    private function getCurrentDatabaseVersion(Driver $driver)
    {
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());
        $pdo = $driver->getPdo();

        $statement = $pdo->query('SELECT version FROM ' . $table . ' WHERE upgradeEnd IS NULL LIMIT 1');
        $version = $statement->fetchColumn();
        if ($version !== false) {
            throw new \RuntimeException('Error: previous upgrade to version ' . $version . ' has not completed.');
        }

        $statement = $pdo->query('SELECT version FROM ' . $table . ' ORDER BY version DESC LIMIT 1');
        $version = $statement->fetchColumn();
        if ($version === false) {
            throw new \RuntimeException('Error: cannot determine the current database version.');
        }

        return (int) $version;
    }

    /**
     * @param Driver  $driver
     * @param integer $version
     *
     * @return void
     */
    private function doCreateResumePoint(Driver $driver, $version)
    {
        $this->output->write('Creating resume point');

        $sqlDumpFilePath = $this->getSqlDumpPath() . '/' . $version . '.sql';
        $fp = fopen($sqlDumpFilePath, 'wb');

        $this->doDumpDatabase($driver, function($query) use ($fp) {
            fwrite($fp, $query . PHP_EOL);
        });

        fclose($fp);
    }

    /**
     * @param Driver  $driver
     * @param integer $version
     *
     * @return void
     */
    private function startUpgrade(Driver $driver, $version)
    {
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());
        $pdo = $driver->getPdo();

        $statement = $pdo->prepare('INSERT INTO ' . $table . ' (version) VALUES(?)');
        $statement->execute(array($version));
    }

    /**
     * @param Driver  $driver
     * @param integer $version
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function endUpgrade(Driver $driver, $version)
    {
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());
        $pdo = $driver->getPdo();

        $statement = $pdo->prepare('UPDATE ' . $table . ' SET upgradeEnd = CURRENT_TIMESTAMP() WHERE version = ?');
        $statement->execute(array($version));

        if ($statement->rowCount() != 1) {
            throw new \RuntimeException('Unexpected number of affected rows');
        }
    }

    /**
     * @return string
     */
    private function getTemporaryDatabaseName()
    {
        return 'temp_' . time();
    }

    /**
     * @param Driver $driver
     *
     * @return void
     */
    private function setDriverExceptionMode(Driver $driver)
    {
        $driver->getPdo()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
}
