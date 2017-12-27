<?php

namespace Urbics\Laracivi\Console\Commands;

use Illuminate\Console\Command;
use Urbics\Laracivi\Console\Migrations\CiviDbBuilder;

class CiviMakeDb extends Command
{
    /**
     * The CiviDbBuilder object
     *
     * @var CiviDbBuilder
     */
    protected $dbBuilder;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'civi:make:db
        {name? : Optional database name.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create and seed schema using civicrm-core *.mysql.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CiviDbBuilder $dbBuilder)
    {
        parent::__construct();
        $this->dbBuilder = $dbBuilder;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = (trim($this->argument('name')) ?: env('CIVI_DB_DATABASE'));
        $result = $this->dbBuilder->build($name);
        $this->info($result['status_message']);
        //
    }
}
