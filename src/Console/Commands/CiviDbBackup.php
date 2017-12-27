<?php

namespace Urbics\Laracivi\Console\Commands;

use DB;
use Exception;
use Illuminate\Console\Command;
use Urbics\Laracivi\Console\Migrations\DbConfig;

class CiviDbBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'civi:db:backup
        {--restore : Restore an existing backup}
        {--database=civicrm : The database to back up}
        {--storage=app/civi : The folder in the storage directory to hold the backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare backup of civi tables.';

    /**
     * The DbConfig instance.
     *
     * @var DbConfig
     */
    protected $dbConfig;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(DbConfig $config)
    {
        parent::__construct();
        $this->dbConfig = $config;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('restore')) {
            return $this->restore();
        }
        return $this->backup();
    }

    protected function backup()
    {
        // Initialize config array for this connection if it does not already exist.
        $this->dbConfig->setDbName($this->option('database'));
        $path = storage_path($this->option('storage'));
        $errorLog = $path . '/mysqldump_errors.log';
        if (!file_exists($path)) {
            mkdir($path);
        }
        if (file_exists($errorLog)) {
            unlink($errorLog);
        }
        $config = $this->dbConfig->getConnection();

        $cmd = "mysqldump -u" . $config['username'] . " -p" . $config['password'] . " -h" . $config['host']
            . " --no-create-db --no-create-info --log-error=$errorLog " . $config['database']
            . " > " . $path . '/current_' . $config['database'] . "_db.sql";
        try {
            exec($cmd);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        if (file_exists($errorLog) and file_get_contents($errorLog)) {
            return $this->comment("Backup of '" . $config['database'] . "' resulted in an error:\n"
                . file_get_contents($errorLog) . "\n");
        }
        return $this->info("Backup of '" . $config['database'] . "' created in '" . $path . "'.");
    }

    protected function restore()
    {
        $conn = $this->option('database');
        $this->dbConfig->setConnectionName($conn);
        $config = config("database.connections.$conn");
        $backupName = storage_path($this->option('storage')) . '/current_' . $config['database'] . "_db.sql";
        if (!file_exists($backupName)) {
            return $this->text("The backup file '" . $backupName . "' was not found.");
        }
        $cmd = "mysql -u" . $config['username'] . " -p" . $config['password'] . " -h" . $config['host']
            . " -v " . $config['database'] . " < $backupName";
        try {
            exec($cmd);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        return $this->info("Restored backup  '" . $backupName . "' to '" . $config['database'] . "'.");
    }
}
