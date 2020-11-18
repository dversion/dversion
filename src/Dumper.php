<?php

declare(strict_types=1);

namespace Dversion;

/**
 * Utility class to dump a database.
 *
 * @internal
 */
class Dumper
{
    const OBJECT_TABLE     = 'table';
    const OBJECT_VIEW      = 'view';
    const OBJECT_TRIGGER   = 'trigger';
    const OBJECT_PROCEDURE = 'procedure';
    const OBJECT_FUNCTION  = 'function';

    /**
     * All the database object types.
     */
    const OBJECT_TYPES = [
        self::OBJECT_TABLE,
        self::OBJECT_VIEW,
        self::OBJECT_TRIGGER,
        self::OBJECT_PROCEDURE,
        self::OBJECT_FUNCTION
    ];

    /**
     * @var \Dversion\Driver
     */
    private $driver;

    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * Class constructor.
     *
     * @param \Dversion\Driver $driver The database driver.
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
        $this->pdo    = $driver->getPdo();
    }

    /**
     * @param string   $name
     * @param callable $output
     *
     * @return void
     */
    public function dumpTableData(string $name, callable $output) : void
    {
        $name = $this->driver->quoteIdentifier($name);
        $statement = $this->pdo->query('SELECT * FROM ' . $name);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $keys = [];
            $values = [];

            foreach ($row as $key => $value) {
                $keys[] = $this->driver->quoteIdentifier($key);
                $values[] = $this->quote($value);
            }

            $keys = implode(', ', $keys);
            $values = implode(', ', $values);

            $output(sprintf('INSERT INTO %s (%s) VALUES(%s);', $name, $keys, $values));
        }
    }

    /**
     * Dumps the database to an output function.
     *
     * @param callable $output A function that will be called with every SQL statement.
     *
     * @return void
     */
    public function dumpDatabase(callable $output) : void
    {
        foreach ($this->driver->getPreDumpSql() as $sql) {
            $output($sql);
        }

        foreach (self::OBJECT_TYPES as $objectType) {
            foreach ($this->driver->getObjects($objectType) as $name) {
                $output($this->driver->getCreateSql($objectType, $name));

                if ($objectType === self::OBJECT_TABLE) {
                    $this->dumpTableData($name, $output);
                }
            }
        }

        foreach ($this->driver->getPostDumpSql() as $sql) {
            $output($sql);
        }
    }

    /**
     * Counts the number of objects to dump, including the table rows.
     *
     * @psalm-param callable(int): void $output
     *
     * @param callable $output A function that will be called for every count.
     *
     * @return void
     */
    public function countObjects(callable $output) : void
    {
        $output(count($this->driver->getPreDumpSql()));

        foreach (self::OBJECT_TYPES as $objectType) {
            $objects = $this->driver->getObjects($objectType);
            $output(count($objects));

            if ($objectType === self::OBJECT_TABLE) {
                foreach ($objects as $tableName) {
                    $tableName = $this->driver->quoteIdentifier($tableName);
                    $rows = $this->pdo->query("SELECT COUNT(*) FROM $tableName")->fetchColumn();
                    $output((int) $rows);
                }
            }
        }

        $output(count($this->driver->getPostDumpSql()));
    }

    /**
     * @param scalar|null $value
     *
     * @return string
     */
    private function quote($value) : string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return (string) (int) $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->pdo->quote($value);
    }
}
