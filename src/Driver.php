<?php

declare(strict_types=1);

namespace Dversion;

/**
 * Interface that all database drivers must implement.
 */
interface Driver
{
    /**
     * Returns the underlying PDO connection.
     *
     * All PDO objects must be configured to throw exceptions as soon as they're created.
     */
    public function getPdo() : \PDO;

    /**
     * Returns the current database name of the underlying PDO connection.
     */
    public function getDatabaseName() : string;

    /**
     * Lists all objects of the given type in the current database.
     *
     * If the platform doesn't support objects of the given type, an empty array must be returned.
     *
     * @return string[] The object names.
     */
    public function getObjects(ObjectType $type) : array;

    /**
     * Returns the SQL to create the object of the given type and name.
     *
     * If the given type is not supported, or the given name does not exist,
     * an exception must be thrown.
     */
    public function getCreateSql(ObjectType $type, string $name) : string;

    /**
     * Creates the version table.
     *
     * If the version table already exists, this method must do nothing,
     * and must not trigger an error or throw an exception.
     */
    public function createVersionTable(string $name) : void;

    /**
     * Returns the list of available databases.
     *
     * @return string[]
     */
    public function listDatabases() : array;

    /**
     * Creates a database of the given name.
     *
     * @param string $name The database name.
     *
     * @return Driver A new driver instance to work with the newly created database.
     */
    public function createDatabase(string $name) : Driver;

    /**
     * Drops the database of the given name.
     *
     * If the database does not exist, this method must do nothing,
     * and must not trigger an error or throw an exception.
     *
     * @param string $name The database name.
     */
    public function dropDatabase(string $name) : void;

    /**
     * Returns an array of SQL statements to insert before the database dump.
     *
     * @return string[]
     */
    public function getPreDumpSql() : array;

    /**
     * Returns an array of SQL statements to insert after the database dump.
     *
     * @return string[]
     */
    public function getPostDumpSql() : array;

    /**
     * Quotes an identifier such as a table name or field name.
     */
    public function quoteIdentifier(string $name) : string;
}
