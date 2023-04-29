<?php

declare(strict_types=1);

namespace Dversion\Driver;

use Dversion\Driver;
use Dversion\ObjectType;
use PDO;

/**
 * Driver for MySQL databases.
 */
final class MySqlDriver implements Driver
{
    private string $host;

    private ?int $port;

    private string $username;

    private string $password;

    private string $database;

    private PDO $pdo;

    private ?string $charset;

    private ?string $collation;

    /**
     * The given database will be created if it does not exist.
     *
     * @param string      $host      The MySQL server host name or IP address.
     * @param string      $username  The MySQL user name.
     * @param string      $password  The MySQL password.
     * @param string      $database  The MySQL database name.
     * @param string|null $charset   An optional charset for the connection and default charset for new databases.
     * @param string|null $collation An optional default collation for new databases.
     * @param int|null    $port      The MySQL port number, or null to use the default.
     */
    public function __construct(string $host, string $username, string $password, string $database, ?string $charset = null, ?string $collation = null, ?int $port = null)
    {
        $this->host      = $host;
        $this->port      = $port;
        $this->username  = $username;
        $this->password  = $password;
        $this->database  = $database;
        $this->charset   = $charset;
        $this->collation = $collation;

        $dsn = 'mysql:host=' . $host;

        if ($port !== null) {
            $dsn .= ';port=' . $port;
        }

        if ($charset !== null) {
            $dsn .= ';charset=' . $charset;
        }

        $this->pdo = new \PDO($dsn, $this->username, $this->password);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $query = 'CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdentifier($database);

        if ($charset !== null) {
            $query .= ' CHARACTER SET ' . $charset;
        }

        if ($collation !== null) {
            $query .= ' COLLATE ' . $collation;
        }

        $this->pdo->exec($query);
        $this->pdo->exec('USE ' . $this->quoteIdentifier($database));
    }

    public function getPdo() : \PDO
    {
        return $this->pdo;
    }

    public function getDatabaseName() : string
    {
        return $this->database;
    }

    public function getObjects(ObjectType $type) : array
    {
        return match ($type) {
            ObjectType::TABLE => $this->fetchArray("SHOW FULL TABLES WHERE Table_type LIKE 'BASE TABLE'", 0),
            ObjectType::VIEW => $this->fetchArray("SHOW FULL TABLES WHERE Table_type LIKE 'VIEW'", 0),
            ObjectType::TRIGGER => $this->fetchArray('SHOW TRIGGERS', 0),
            ObjectType::PROCEDURE => $this->fetchArray('SHOW PROCEDURE STATUS WHERE Db = (SELECT DATABASE())', 1),
            ObjectType::FUNCTION => $this->fetchArray('SHOW FUNCTION STATUS WHERE Db = (SELECT DATABASE())', 1),
        };
    }

    public function getCreateSql(ObjectType $type, string $name) : string
    {
        return match ($type) {
            ObjectType::TABLE => $this->fetchColumn('SHOW CREATE TABLE', 1, $name),
            ObjectType::VIEW => $this->fetchColumn('SHOW CREATE VIEW', 1, $name),
            ObjectType::TRIGGER => $this->fetchColumn('SHOW CREATE TRIGGER', 2, $name),
            ObjectType::PROCEDURE => $this->fetchColumn('SHOW CREATE PROCEDURE', 2, $name),
            ObjectType::FUNCTION => $this->fetchColumn('SHOW CREATE FUNCTION', 2, $name),
        };
    }

    public function createVersionTable(string $name) : void
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

    public function listDatabases() : array
    {
        return $this->fetchArray('SHOW DATABASES', 0);
    }

    public function createDatabase(string $name) : Driver
    {
        return new MySqlDriver($this->host, $this->username, $this->password, $name, $this->charset, $this->collation, $this->port);
    }

    public function dropDatabase(string $name) : void
    {
        $this->pdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($name));
    }

    public function getPreDumpSql() : array
    {
        return ['SET foreign_key_checks = 0;'];
    }

    public function getPostDumpSql() : array
    {
        return ['SET foreign_key_checks = 1;'];
    }

    public function quoteIdentifier(string $name) : string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Executes the given query and returns the data in the given column index.
     *
     * @param string $query  The query to execute.
     * @param int    $column The column index to return.
     *
     * @return string[]
     */
    private function fetchArray(string $query, int $column) : array
    {
        return array_map(function($value) {
            return (string) $value;
        }, $this->pdo->query($query)->fetchAll(\PDO::FETCH_COLUMN, $column));
    }

    /**
     * @param string $query  The query to execute.
     * @param int    $column The column index to return.
     * @param string $name   The object name to quote and add to the query.
     */
    private function fetchColumn(string $query, int $column, string $name) : string
    {
        $query .= ' ' . $this->quoteIdentifier($name);

        $value = (string) $this->pdo->query($query)->fetchColumn($column);

        return $value . ';';
    }
}
