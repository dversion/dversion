<?php

declare(strict_types=1);

namespace Dversion;

use PDO;

/**
 * Utility class to dump a database.
 *
 * @internal
 */
class Dumper
{
    public const OBJECT_TABLE     = 'table';
    public const OBJECT_VIEW      = 'view';
    public const OBJECT_TRIGGER   = 'trigger';
    public const OBJECT_PROCEDURE = 'procedure';
    public const OBJECT_FUNCTION  = 'function';

    /**
     * All the database object types.
     */
    public const ALL_OBJECT_TYPES = [
        self::OBJECT_TABLE,
        self::OBJECT_VIEW,
        self::OBJECT_TRIGGER,
        self::OBJECT_PROCEDURE,
        self::OBJECT_FUNCTION
    ];

    private Driver $driver;

    private PDO $pdo;

    /**
     * @param Driver $driver The database driver.
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
        $this->pdo    = $driver->getPdo();
    }

    /**
     * @param callable(string): void $output
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
     * @psalm-param callable(string): void $output
     */
    public function dumpDatabase(callable $output) : void
    {
        foreach ($this->driver->getPreDumpSql() as $sql) {
            $output($sql);
        }

        foreach (self::ALL_OBJECT_TYPES as $objectType) {
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
     */
    public function countObjects(callable $output) : void
    {
        $output(count($this->driver->getPreDumpSql()));

        foreach (self::ALL_OBJECT_TYPES as $objectType) {
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
