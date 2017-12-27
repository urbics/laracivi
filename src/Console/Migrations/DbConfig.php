<?php
namespace Urbics\Laracivi\Console\Migrations;

use Illuminate\Config\Repository as Repository;

class DbConfig
{
    protected $config;
    protected $connectName;
    protected $connection;
    protected $package;

    public function __construct(Repository $config)
    {
        $this->config = $config;
        $this->connectName = env('CIVI_DB_CONNECTION');
        $this->package = env('CIVI_CORE_PACKAGE');
        $this->setConnection();
    }

    public function connectionName()
    {
        return $this->connectName;
    }

    public function setConnectionName($name = '')
    {
        $this->connectName = $name ?: $this->connectName;
        $this->setConnection();
    }

    public function dbName()
    {
        return config("database.connections.{$this->connectName}.database");
    }

    public function packagePath()
    {
        return (base_path("vendor/{$this->package}"));
    }

    public function sqlPath()
    {
        return ($this->packagePath() . '/sql');
    }

    public function setDbName($dbName)
    {
        $curName = $this->dbName();
        config(["database.connections.{$this->connectName}.database" => $dbName]);
        return $curName;
    }

    protected function setConnection()
    {
        if (!config("database.connections.{$this->connectName}")) {
            config(["database.connections.{$this->connectName}" => [
                'driver'    => 'mysql',
                'host'      => env('CIVI_DB_HOST'),
                'database'  => env('CIVI_DB_DATABASE'),
                'username'  => env('CIVI_DB_USERNAME'),
                'password'  => env('CIVI_DB_PASSWORD'),
                'port'      => env('CIVI_DB_PORT'),
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ]]);
        }
    }

    public function getConnection()
    {
        return config("database.connections.{$this->connectName}");
    }

}
