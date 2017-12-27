<?php
namespace Urbics\Laracivi\Console\Migrations;

use Urbics\Laracivi\Console\Migrations\DbConfig;
use Urbics\Laracivi\Console\Migrations\CiviDbSeeder;
use Illuminate\Database\DatabaseManager as DB;
use Urbics\Laracivi\Traits\StatusMessageTrait;

class CiviDbBuilder
{
    use StatusMessageTrait;

    protected $civiConfig;
    protected $seeder;
    protected $db;

    public function __construct(DbConfig $config, CiviDbSeeder $seeder, DB $db)
    {
        $this->civiConfig = $config;
        $this->seeder = $seeder;
        $this->db = $db;
    }

    /**
     * Create and seed the civicrm schema from civicrm-core sql.
     *
     * @return array
     */
    public function build($dbName)
    {
        if (!$dbName) {
            return [
                'status_code' => $this->getFailStatusCode(),
                'status_message' => "You must provide a database name."
            ];

        }
        $this->civiConfig->setDbName($dbName);
        if ($this->dbExists()) {
            $dbName = $this->civiConfig->dbName();
            return [
                'status_code' => $this->getSuccessStatusCode(),
                'status_message' => "Database '{$dbName}' already exists.  No changes made."
            ];
        }
        $this->createDb();
        $this->seeder->seed(true);

        return [
            'status_code' => $this->getSuccessStatusCode(),
            'status_message' => "CiviCRM database created.",
        ];
    }

    /**
     * Checks existence of civicrm database.
     *
     * @return boolean
     */
    protected function dbExists()
    {
        // Remove db name from connection to avoid sql errors if db does not yet exist.
        $dbName = $this->civiConfig->setDbName('');
        $conn = $this->civiConfig->connectionName();
        $result = $this->db->reconnect($conn)->select(
            "select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = :name",
            ['name' => $dbName]
        );
        $this->civiConfig->setDbName($dbName);

        return (!empty($result));
    }

    /**
     * Creates the civicrm database.
     */
    protected function createDb()
    {
        // Create database and reload connection, using an empty db name in the connection.
        $dbName = $this->civiConfig->setDbName('');
        $conn = $this->civiConfig->connectionName();
        $this->db->reconnect($conn)->statement('create database if not exists ' . $dbName);
        $this->civiConfig->setDbName($dbName);
        $this->db->reconnect($conn);
    }
}
