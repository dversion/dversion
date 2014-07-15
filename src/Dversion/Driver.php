<?php

namespace Dversion;

/**
 * Interface that all database drivers must implement.
 */
interface Driver
{
    /**
     * Returns the underlying PDO connection.
     *
     * @return \PDO
     */
    public function getPdo();

    /**
     * Returns the current database name of the underlying PDO connection.
     *
     * @return string
     */
    public function getDatabaseName();

    /**
     * Lists all objects of the given type in the current database.
     *
     * If the platform doesn't support objects of the given type, an empty array must be returned.
     *
     * @param string $type One of the Database::OBJECT_* constants.
     *
     * @return array The object names.
     */
    public function getObjects($type);

    /**
     * Returns the SQL to create the object of the given type and name.
     *
     * If the given type is not supported, or the given name does not exist,
     * an exception must be thrown.
     *
     * @param string $type The object type, one of the Database::OBJECT_* constants.
     * @param string $name The object name.
     *
     * @return string
     */
    public function getCreateSql($type, $name);

    /**
     * Creates the version table.
     *
     * If the version table already exists, this method must do nothing,
     * and must not trigger an error or throw an exception.
     *
     * @param string $name
     *
     * @return void
     */
    public function createVersionTable($name);

    /**
     * Creates a database of the given name.
     *
     * @param string $name The database name.
     *
     * @return \Dversion\Driver A new driver instance to work with the newly created database.
     */
    public function createDatabase($name);

    /**
     * Drops the database of the given name.
     *
     * If the database does not exist, this method must do nothing,
     * and must not trigger an error or throw an exception.
     *
     * @param string $name The database name.
     *
     * @return void
     */
    public function dropDatabase($name);

    /**
     * Returns an array of SQL statements to insert before the database dump.
     *
     * @return array
     */
    public function getPreDumpSql();

    /**
     * Returns an array of SQL statements to insert after the database dump.
     *
     * @return array
     */
    public function getPostDumpSql();

    /**
     * Quotes an identifier such as a table name or field name.
     *
     * @param string $name
     *
     * @return string
     */
    public function quoteIdentifier($name);
}
