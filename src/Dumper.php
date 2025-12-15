<?php

declare(strict_types=1);

namespace Dversion;

use PDO;

/**
 * Utility class to dump a database.
 *
 * @internal
 */
final readonly class Dumper
{
    private PDO $pdo;

    /**
     * @param Driver $driver The database driver.
     */
    public function __construct(
        private Driver $driver,
    ) {
        $this->pdo = $this->driver->getPdo();
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

        foreach (ObjectType::cases() as $objectType) {
            foreach ($this->driver->getObjects($objectType) as $name) {
                $output($this->driver->getCreateSql($objectType, $name));

                if ($objectType === ObjectType::TABLE) {
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

        foreach (ObjectType::cases() as $objectType) {
            $objects = $this->driver->getObjects($objectType);
            $output(count($objects));

            if ($objectType === ObjectType::TABLE) {
                foreach ($objects as $tableName) {
                    $tableName = $this->driver->quoteIdentifier($tableName);
                    /** @var string|int $rows */
                    $rows = $this->pdo->query("SELECT COUNT(*) FROM $tableName")->fetchColumn();
                    $output((int) $rows);
                }
            }
        }

        $output(count($this->driver->getPostDumpSql()));
    }

    private function quote(string|int|float|bool|null $value) : string
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
