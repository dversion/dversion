<?php

namespace Dversion\Driver;

use Dversion\Dumper;
use Dversion\Driver;

/**
 * Driver for MySQL databases.
 */
class MySqlDriver implements Driver
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $database;

    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var string|null
     */
    private $charset;

    /**
     * @var string|null
     */
    private $collation;

    /**
     * Class constructor.
     *
     * The given database will be created if it does not exist.
     *
     * @param string      $host      The MySQL server host name or IP address.
     * @param string      $username  The MySQL user name.
     * @param string      $password  The MySQL password.
     * @param string      $database  The MySQL database name.
     * @param string|null $charset   An optional charset for the connection and default charset for new databases.
     * @param string|null $collation An optional default collation for new databases.
     */
    public function __construct($host, $username, $password, $database, $charset = null, $collation = null)
    {
        $this->host      = $host;
        $this->username  = $username;
        $this->password  = $password;
        $this->database  = $database;
        $this->charset   = $charset;
        $this->collation = $collation;

        $dsn = 'mysql:host=' . $this->host;

        if ($charset !== null) {
            $dsn .= ';charset=' . $charset;
        }

        $this->pdo = new \PDO($dsn, $this->username, $this->password);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $query = 'CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdentifier($database);

        if ($charset === null) {
            $query .= ' CHARACTER SET ' . $charset;
        }

        if ($collation !== null) {
            $query .= ' COLLATE ' . $collation;
        }

        $this->pdo->exec($query);
        $this->pdo->exec('USE ' . $this->quoteIdentifier($database));
    }

    /**
     * {@inheritdoc}
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName()
    {
        return $this->database;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjects($type)
    {
        switch ($type) {
            case Dumper::OBJECT_TABLE:
                return $this->fetchArray("SHOW FULL TABLES WHERE Table_type LIKE 'BASE TABLE'", 0);

            case Dumper::OBJECT_VIEW:
                return $this->fetchArray("SHOW FULL TABLES WHERE Table_type LIKE 'VIEW'", 0);

            case Dumper::OBJECT_TRIGGER:
                return $this->fetchArray('SHOW TRIGGERS', 0);

            case Dumper::OBJECT_PROCEDURE:
                return $this->fetchArray('SHOW PROCEDURE STATUS WHERE Db = (SELECT DATABASE())', 1);

            case Dumper::OBJECT_FUNCTION:
                return $this->fetchArray('SHOW FUNCTION STATUS WHERE Db = (SELECT DATABASE())', 1);
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateSql($type, $name)
    {
        switch ($type) {
            case Dumper::OBJECT_TABLE:
                return $this->fetchColumn('SHOW CREATE TABLE', 1, $name);

            case Dumper::OBJECT_VIEW:
                return $this->fetchColumn('SHOW CREATE VIEW', 1, $name);

            case Dumper::OBJECT_TRIGGER:
                return $this->fetchColumn('SHOW CREATE TRIGGER', 2, $name);

            case Dumper::OBJECT_PROCEDURE:
                return $this->fetchColumn('SHOW CREATE PROCEDURE', 2, $name);

            case Dumper::OBJECT_FUNCTION:
                return $this->fetchColumn('SHOW CREATE FUNCTION', 2, $name);
        }

        throw new \InvalidArgumentException('Unsupported object type.');
    }

    /**
     * {@inheritdoc}
     */
    public function createVersionTable($name)
    {
        $name = $this->quoteIdentifier($name);

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS $name (
                version INT(10) UNSIGNED NOT NULL,
                upgradeStart TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                upgradeEnd TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB;
        ");
    }

    /**
     * {@inheritdoc}
     */
    public function listDatabases()
    {
        return $this->fetchArray('SHOW DATABASES', 0);
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name)
    {
        return new MySqlDriver($this->host, $this->username, $this->password, $name, $this->charset, $this->collation);
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->pdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($name));
    }

    /**
     * {@inheritdoc}
     */
    public function getPreDumpSql()
    {
        return array('SET foreign_key_checks = 0;');
    }

    /**
     * {@inheritdoc}
     */
    public function getPostDumpSql()
    {
        return array('SET foreign_key_checks = 1;');
    }

    /**
     * {@inheritdoc}
     */
    public function quoteIdentifier($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Executes the given query and returns the data in the given column index.
     *
     * @param string  $query  The query to execute.
     * @param integer $column The column index to return.
     *
     * @return array
     */
    private function fetchArray($query, $column)
    {
        return $this->pdo->query($query)->fetchAll(\PDO::FETCH_COLUMN, $column);
    }

    /**
     * @param string  $query  The query to execute.
     * @param integer $column The column index to return.
     * @param string  $name   The object name to quote and add to the query.
     *
     * @return string
     */
    private function fetchColumn($query, $column, $name)
    {
        $name = $this->quoteIdentifier($name);

        return $this->pdo->query($query . ' ' . $name)->fetchColumn($column) . ';';
    }
}