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
     * @param integer $version The database version when using an existing database, 0 for a pristine database.
     *
     * @return integer
     */
    public function init($version)
    {
        $targetDriver = $this->configuration->getDriver();

        $this->output->writeln('Creating the versioning table');
        $targetDriver->createVersionTable($this->configuration->getVersionTableName());

        $this->output->writeln('Writing version numbers');
        for ($i = 1; $i <= $version; $i++) {
            $this->startUpdate($targetDriver, $i);
            $this->endUpdate($targetDriver, $i);
        }

        $this->output->writeln('Success!');
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
            $returnCode = 1;
        } elseif ($currentVersion == $latestVersion) {
            $this->output->writeln('The database is up to date!');
            $returnCode = 0;
        } else {
            $this->runUpdates($targetDriver, $currentVersion, $latestVersion);
            $this->output->writeln('Success!');
            $returnCode = 0;
        }

        if ($test) {
            $this->dropDatabase($temporaryDatabaseName);
        }

        return $returnCode;
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
            $currentVersion = $this->resume($targetDriver);
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
            $currentVersion = $this->resume($targetDriver);
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
     * @return integer
     */
    public function cleanup()
    {
        $driver = $this->configuration->getDriver();
        $databases = $driver->listDatabases();

        foreach ($databases as $database) {
            if (preg_match($this->getTemporaryDatabasePattern(), $database)) {
                $this->dropDatabase($database);
            }
        }

        return 0;
    }

    /**
     * @param string $direction
     *
     * @return integer
     */
    public function resolve($direction)
    {
        $driver = $this->configuration->getDriver();

        $pdo = $driver->getPdo();
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());

        $version = $this->getFailedDatabaseVersion($driver);

        if ($version === null) {
            throw new \RuntimeException('There is nothing to resolve.');
        }

        switch ($direction) {
            case 'backward':
                $statement = $pdo->prepare("DELETE FROM $table WHERE version = ?");
                $statement->execute(array($version));
                $version--;
                break;

            case 'forward':
                $statement = $pdo->prepare("UPDATE $table SET upgradeEnd = CURRENT_TIMESTAMP() WHERE version = ?");
                $statement->execute(array($version));
                break;

            default:
                throw new \RuntimeException(
                    'Invalid resolve direction: ' . $direction . PHP_EOL .
                    'Valid values are backward and forward.'
                );
        }

        $this->output->writeln(sprintf('Successfully resolved to version %d.', $version));

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
        $progress = new ProgressBar($this->output, $latestVersion - $currentVersion);
        $progress->setFormat('%message% [%bar%] %version% %percent:3s%%');
        $progress->setMessage('Applying version');
        $progress->setMessage('', 'version');
        $progress->start();

        for ($version = $currentVersion + 1; $version <= $latestVersion; $version++) {
            $progress->setMessage("$version/$latestVersion", 'version');
            $progress->display();
            $sqlFilePath = $this->getSqlPath() . '/' . $version . '.sql';

            $this->startUpdate($targetDriver, $version);
            $this->importSqlFile($targetDriver->getPdo(), $sqlFilePath);
            $this->endUpdate($targetDriver, $version);

            $progress->advance();
        }

        $progress->finish();
        $this->output->writeln('');
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
        }, 'Copying database');
    }

    /**
     * @param Driver   $sourceDriver The source driver.
     * @param callable $output       A function to call with every SQL statement.
     * @param string   $message      The message going next to the progress bar.
     *
     * @return void
     */
    private function doDumpDatabase(Driver $sourceDriver, $output, $message)
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
        $progress->setMessage($message);
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
        $versions = $this->getFileVersions($this->getSqlPath(), 'sql');
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
        $versions = $this->getFileVersions($this->getSqlDumpPath(), 'tar');

        if (count($versions) == 0) {
            throw new \RuntimeException('Cannot find SQL dump file');
        }

        return end($versions);
    }

    /**
     * @param string $directory
     * @param string $extension
     *
     * @return array
     */
    private function getFileVersions($directory, $extension)
    {
        $files = new \DirectoryIterator($directory);
        $versions = array();

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() == $extension) {
                $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                if (ctype_digit($filename)) {
                    $versions[] = (int) $filename;
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
        $statement = $pdo->query($sql);

        // If the SQL file contains several queries, and an error occurs on any query but the first one,
        // only looping through the rowsets will throw a PDOException for this query.
        while ($statement->nextRowset());
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

        $version = $this->getFailedDatabaseVersion($driver);

        if ($version !== null) {
            throw new \RuntimeException(
                'Error: previous update to version ' . $version . ' has not completed.' . PHP_EOL .
                'Resolve the problem manually then use the resolve command.' . PHP_EOL .
                'Run dversion help resolve for more information.'
            );
        }

        $statement = $pdo->query('SELECT version FROM ' . $table . ' ORDER BY version DESC LIMIT 1');
        $version = $statement->fetchColumn();
        if ($version === false) {
            throw new \RuntimeException('Error: cannot determine the current database version.');
        }

        return (int) $version;
    }

    /**
     * @param Driver $driver
     *
     * @return integer|null The failed database version, or null if no update has failed.
     */
    private function getFailedDatabaseVersion(Driver $driver)
    {
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());
        $pdo = $driver->getPdo();

        $statement = $pdo->query('SELECT version FROM ' . $table . ' WHERE upgradeEnd IS NULL LIMIT 1');
        $version = $statement->fetchColumn();

        return ($version === false) ? null : (int) $version;
    }

    /**
     * @param Driver  $driver
     * @param integer $version
     *
     * @return void
     */
    private function doCreateResumePoint(Driver $driver, $version)
    {
        $directory = $this->getTemporaryName();
        mkdir($directory);

        $number = 1;
        $that = $this;

        $this->doDumpDatabase($driver, function($query) use ($that, $directory, & $number) {
            file_put_contents($that->getSqlFilePath($directory, $number), $query);
            $number++;
        }, 'Dumping database');

        $this->output->writeln('Creating archive file');

        $archiveFile = $this->getSqlDumpPath() . '/' . $version . '.tar';

        $phar = new \PharData($archiveFile);
        $phar->buildFromDirectory($directory);

        $this->output->writeln('Removing temporary files');

        for ($i = 1; $i < $number; $i++) {
            unlink($this->getSqlFilePath($directory, $i));
        }

        rmdir($directory);
    }

    /**
     * Resumes the latest database dump.
     *
     * @param Driver $driver
     *
     * @return integer The version resumed.
     *
     * @throws \RuntimeException
     */
    private function resume(Driver $driver)
    {
        $version = $this->getDumpVersion();
        $this->output->writeln('Resuming at version ' . $version);
        $archivePath = $this->getSqlDumpPath() . '/' . $version . '.tar';

        $phar = new \PharData($archivePath);

        $progress = new ProgressBar($this->output, $phar->count());
        $progress->setMessage('Resuming version');
        $progress->setFormat('%message% [%bar%] %current%/%max% %percent:3s%%');
        $progress->start();

        foreach ($phar as $path => $file) {
            $this->importSqlFile($driver->getPdo(), $path);
            $progress->advance();
        }

        $progress->finish();
        $this->output->writeln('');

        if ($version != $this->getCurrentDatabaseVersion($driver)) {
            throw new \RuntimeException('Error resuming version ' . $version);
        }

        return $version;
    }

    /**
     * @param string  $directory
     * @param integer $version
     *
     * @return string
     */
    public function getSqlFilePath($directory, $version)
    {
        return $directory . DIRECTORY_SEPARATOR . sprintf('%06u.sql', $version);
    }

    /**
     * @return string
     */
    private function getTemporaryName()
    {
        do {
            $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('dversion_');
        }
        while (file_exists($directory));

        return $directory;
    }

    /**
     * @param Driver  $driver  The target driver.
     * @param integer $version The version being applied.
     *
     * @return void
     */
    private function startUpdate(Driver $driver, $version)
    {
        $pdo = $driver->getPdo();
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());

        $statement = $pdo->prepare("INSERT INTO $table (version) VALUES (?)");
        $statement->execute(array($version));
    }

    /**
     * @param Driver  $driver  The target driver.
     * @param integer $version The version being applied.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function endUpdate(Driver $driver, $version)
    {
        $pdo = $driver->getPdo();
        $table = $driver->quoteIdentifier($this->configuration->getVersionTableName());

        $statement = $pdo->prepare("UPDATE $table SET upgradeEnd = CURRENT_TIMESTAMP() WHERE version = ?");
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
     * @return string
     */
    private function getTemporaryDatabasePattern()
    {
        return '/^temp_[0-9]{10}$/';
    }
}
