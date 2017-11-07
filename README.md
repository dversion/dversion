# Dversion

<img src="https://avatars.githubusercontent.com/u/8125716?s=128" alt="" align="left" height="64">

A database versioning tool for PHP applications.

[![Latest Stable Version](https://poser.pugx.org/dversion/dversion/v/stable)](https://packagist.org/packages/dversion/dversion)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](http://opensource.org/licenses/MIT)

## Introduction

This tool allows you to version your database by creating sequential SQL files.
This allows you to keep your local and production databases in sync, and re-create an up-to-date database from scratch when setting up a new development machine.

This tool is written in PHP, and mostly suited for being included in your PHP applications.
It requires PHP 7.1 or later. Although extensible to other database vendors, it only supports MySQL for now.

## Setup

- Install dversion with Composer: `composer require dversion/dversion`
- Create a `sql` directory at the root of your project
- Create a `dversion.php` configuration file at the root of your project:

```php
<?php

use Dversion\Configuration;
use Dversion\Driver\MySqlDriver;

return Configuration::create(new MySqlDriver(
    'hostname',
    'username',
    'password',
    'database',
    'utf8mb4',
    'utf8mb4_unicode_ci'
));
```

Replace the parameters with your MySQL connection parameters.
The last 2 parameters are the charset and collation; these are optional, but highly recommended to consistently create your databases with the proper charset.

If you need to, you can change the `sql` directory name or location:

```php
return Configuration::create(...)->setSqlDirectory('path/to/sql/dir');
```

Dversion needs to store the current version in a database table. By default, this table is named `version_history`. You can change this name on the Configuration object as well:

```php
return Configuration::create(...)->setVersionTableName('version_table_name');
```

## Usage

You can start writing your sequential SQL version files in the `sql` directory:

- `1.sql`
- `2.sql`
- …

The first version file will probably create some tables, while subsequent version files may create new tables, update existing tables, etc.

### Check the current database status

```bash
vendor/bin/dversion status
```

```
Current database version: 123
Latest database version: 123
The database is up to date!
```

### Update the database to the latest version

```bash
vendor/bin/dversion update
```

```
Current database version: 430
Applying version [============================] 432/432 100%
Success!
```

You can add the `--test` parameter to have dversion create a copy of your database, and perform the update in this copy:

```bash
vendor/bin/dversion update --test
```

```
Creating database temp_1510058204
Counting objects [============================]  657
Copying database [============================] 657/657 100%
Current database version: 430
Applying version [============================] 432/432 100%
Success!
Dropping database temp_1510058204
```

This allows you to check that the SQL patch you just wrote runs fine, without altering your working database.
Be careful that copying a large database can be a lengthy process, so `--test` if primarily intended for development, not for your production database.

### Reset the database

This is useful to reset your development machine to a fresh database. **Do not use this on a production database!**

```bash
vendor/bin/dversion reset
```

```
Dropping database example
Creating database example
Creating the versioning table
Applying version [============================] 432/432 100%
```

You can add the `--test` parameter to have dversion create a temporary database, and perform the reset in this database:

```bash
vendor/bin/dversion reset --test
```

```
Creating database temp_1510058307
Creating the versioning table
Applying version [============================] 432/432 100%
Dropping database temp_1510058307
Success!
```

This is useful to ensure that your whole SQL patch history runs without errors, without affecting your working database.

### Resolve a failed update

When a database update could not be completed due to an error, dversion cannot handle the situation automatically.

You must check and fix what went wrong, and manually update your database to bring it to a known state. Then you must tell dversion how you handled the issue:

- if you manually reverted the changes partially applied by the patch, run `dversion resolve backward`
- if you manually applied the rest of the patch, run `dversion resolve forward`

This command really just sets the current database version. You are responsible for bringing the database manually to a known state.
For example, if update to version 7 failed, resolve backward will set the current version to 6, while resolve forward will set the current version to 7.

### Clean up the temporary databases

When `update --test` or `reset --test` fails, the temporary database is not removed, to allow you to investigate the failure.
You can request dversion to clean up all the temporary databases (all the databases whose name start with `temp_`):

```bash
vendor/bin/dversion cleanup
```

```
Dropping database temp_1510058474
Dropping database temp_1510058476
```

### Create a resume point

When your project has a big SQL patch history, resetting your local development database can be time consuming.
For this reason, dversion can create a resume point, that is, a snapshot of your database at a given version:

```bash
vendor/bin/dversion create-resume-point
```

```
Creating database temp_1510058736
Creating the versioning table
Applying version [============================] 432/432 100%
Counting objects [============================]  659
Dumping database [============================] 659/659 100%
Creating archive file
Removing temporary files
Dropping database temp_1510058736
Success!
```

A `.tar` file matching the current database version will be created in a `sql/dump` directory (in this case, `sql/dump/432.sql`).

You can later reset your database from this resume point:

```bash
vendor/bin/dversion reset --resume
```

```
Dropping database example
Creating database example
Resuming at version 432
Resuming version [============================] 659/659 100%
Applying version [>---------------------------]    0%
Success!
```

### Initialize dversion on a legacy database

If you want to start versioning an existing project with dversion, you can dump the structure of your existing database in `sql/1.sql`, and run:


```bash
vendor/bin/dversion init 1
```

```
Creating the versioning table
Writing version numbers
Success!
```

This will create the versioning table, and write the current version number. You can start updating your database normally from there.

## Best practices

These are a few best practices you should follow when versioning your database with dversion:

### Version control

All your `.sql` scripts should be committed in your VCS (Git, SVN, …). If someone else tries to commit/push a `.sql` script with the same name as yours (conflicting versions),
they should get a conflict in the VCS, that must be resolved manually. This conflict is expected, and essential to the consistency of your SQL patch history:
SQL files are sequential, and two conflicting version patches might not be compatible. The code must be rewritten to create 2 patches that will execute sequentially and without errors or unexpected side effects.

If you use resume points, you should commit the latest resume point file (`sql/dump/xxx.sql`) in your VCS as well. Older resume points can be removed when a new one is created.

### SQL files contents

Most of the time, your SQL files should only contain the table structure and updates to this structure (DDL).
It may occasionally contain a few records, for example a hardcoded list of countries that cannot be updated from the application.

**As a rule of thumb, the only records that should appear in your SQL scripts are those that can only be updated from there.**
If the same records can be updated from your application, you're opening a can of worms.

**Do not include your sample data in your SQL version files.** Sample data should come from another mechanism, used only in development.
