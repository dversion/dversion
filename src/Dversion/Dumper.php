<?php

namespace Dversion;

/**
 * Utility class to dump a database.
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
     *
     * @var array
     */
    private static $objectTypes = array(
        self::OBJECT_TABLE,
        self::OBJECT_VIEW,
        self::OBJECT_TRIGGER,
        self::OBJECT_PROCEDURE,
        self::OBJECT_FUNCTION
    );

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
    public function dumpTableData($name, $output)
    {
        $name = $this->driver->quoteIdentifier($name);
        $statement = $this->pdo->query('SELECT * FROM ' . $name);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $keys = array();
            $values = array();

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
     * @param callable $output
     *
     * @return void
     */
    public function dumpDatabase($output)
    {
        foreach ($this->driver->getPreDumpSql() as $sql) {
            $output($sql);
        }

        foreach (self::$objectTypes as $objectType) {
            foreach ($this->driver->getObjects($objectType) as $name) {
                $output($this->driver->getCreateSql($objectType, $name));

                if ($objectType == self::OBJECT_TABLE) {
                    $this->dumpTableData($name, $output);
                }
            }
        }

        foreach ($this->driver->getPostDumpSql() as $sql) {
            $output($sql);
        }
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_integer($value)) {
            return (string) $value;
        }

        return $this->pdo->quote($value);
    }
}
