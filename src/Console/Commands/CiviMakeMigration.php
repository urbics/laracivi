<?php

namespace Urbics\Laracivi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Urbics\Laracivi\Console\Migrations\SchemaParser;
use Urbics\Laracivi\Console\Migrations\SyntaxBuilder;

/**
 * Based on laracasts/generators
 * DOMDocument should be available by default in php > 5.?
 */
class CiviMakeMigration extends Command
{
    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * The XML schema file name.
     *
     * @var string
     */
    protected $xmlSource;

    /**
     * The path for the migration files.
     *
     * @var string
     */
    protected $path;

    /**
     * The path for the seed files.
     *
     * @var string
     */
    protected $seedPath;

    /**
     * Build Eloquent models?
     *
     * @var boolean
     */
    protected $model;

    /**
     * Schema used to build migrations
     *
     * @var array
     */
    protected $schema;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * Array of tables with seed classes added.
     *
     * @var array
     */
    private $seedTables = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'civi:make:migration  
        {--xml=Schema.xml : XML schema file} 
        {--path=civi : Path to stored migration files (under the database/migrations folder)} 
        {--model : Build Eloquent model for each CiviCRM table.}
        {--model-path=Models/Civi : Path to stored models} 
        {--seed : Build Seed class for each CiviCRM table.}
        {--seed-db-init=CiviDefaultSeeder : Seeder class containing the CiviCRM-generated seeders.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate civicrm migration files from xml schema';

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;

        $this->composer = app()['composer'];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $civiCore = config('civi.package');
        $this->xmlSource = base_path('vendor/' . $civiCore . '/xml/schema/' . $this->option('xml'));
        $this->path = database_path("migrations/" . $this->option('path'));
        $this->seedPath = database_path("seeds/" . $this->option('path'));
        $this->model = $this->option('model');

        $this->makeSchema();
        $this->process();
    }

    /**
     * Generate the schema from xml source.
     */
    protected function makeSchema()
    {
        $dom = new \DomDocument();
        $xmlString = file_get_contents($this->xmlSource);
        $dom->loadXML($xmlString);
        $dom->documentURI = $this->xmlSource;
        $dom->xinclude();
        $this->schema = (new SchemaParser)->parse(simplexml_import_dom($dom));
    }

    /**
     * Generate the requested files.
     */
    protected function process()
    {
        $this->makeDirectory($this->path);
        $this->makeDirectory($this->seedPath);

        $this->seederSetup();

        // Run the table and index migrations and the model and seeder for each table.
        foreach ($this->schema['create'] as $table) {
            if (!empty($table['drop'])) {
                continue;
            }
            $this->info("Processing " . $table['name']);
            $this->makeMigration($table, 'create');
            $this->makeModel($table);
            $this->makeSeeder($table);
        }

        $this->seederCleanup();

        // Generate the foreign key migration classes.
        foreach ($this->schema['update'] as $table) {
            if (!empty($table['drop'])) {
                continue;
            }
            $this->info("Processing foreign keys for " . $table['name']);
            $this->makeMigration($table, 'update');
        }

        $this->info("Running composer dumpautoloads");
        $this->composer->dumpAutoloads();

        $this->info('Finished.');
    }

    /**
     * Generates a migration for this table
     *
     * @param  array $table
     * @return null
     */
    protected function makeMigration($table, $action)
    {
        $dirList = $this->files->allFiles(database_path('migrations/' . $this->option('path')));
        foreach ($dirList as $file) {
            if (strpos($file, $action . '_' . $table['name'] . '.php')) {
                $this->comment('Migration already exists.');
                return;
            }
        }
        $this->files->put($this->getFilename($table['name'], $action), $this->compileMigrationStub($table, $action));
        $this->info('Migration created successfully.');
    }

    /**
     * Generate an Eloquent model, if the user wishes.
     * Model is placed in Models/Civi by default.
     * Use model-path option to change this.
     *
     * @param array $table
     */
    protected function makeModel($table)
    {
        if (!$this->option('model')) {
            return;
        }
        $modelPath = $this->getModelPath($this->getModelName($table));
        if ($this->files->exists($modelPath)) {
            $this->comment('Model already exists.');
            return;
        }
        $this->call('civi:make:model', [
            'name' => ($this->option('model-path') ? $this->option('model-path') . '/': '') . $this->getModelName($table),
        ]);
    }

    protected function seederSetup()
    {
        if ($this->option('seed')) {
            $this->seedTables[] = "\$this->call(" . $this->option('seed-db-init') . "::class);";
        }
    }

    /**
     * Generate a Seed class if requested.
     * Seeder is placed in database/seeds/civi by default, or another subdirectory identified in --seed-path
     *
     * @param array $table
     */
    protected function makeSeeder($table)
    {
        if (!$this->option('seed')) {
            return;
        }
        /* Same name casing as ModelName */
        $className = $this->getModelName($table) . 'Seeder';
        $seederPath = $this->getSeederPath($className);
        $this->seedTables[] = "\$this->call($className::class);";
        if ($this->files->exists($seederPath)) {
            $this->comment('Seeder already exists.');
            return;
        }
        $this->call('civi:make:seeder', [
            'name' => $className,
            '--path' => $this->option('path'),
        ]);
    }


    /**
     * Generate additional seeders:
     *  - CiviCRM default values in the class name passed by seed-db-init
     *  - CiviDatabaseSeeder: runs civi seeders (except for the default seeder,
     *      these have no content initially and are commented out - uncomment to run them)
     *  - DatabaseSeeder: includes the CiviDatabaseSeeder run command.
     *
     * @return [type]
     */
    public function seederCleanup()
    {
        if (empty($this->seedTables)) {
            return;
        }

        // Generate the CiviCRM default seeder
        $name = $this->getModelName(['name' => $this->option('seed-db-init')]);
        $this->info("Processing " . $name);
        if ($this->files->exists(database_path('seeds/' . $this->option('path') . '/' . $name . '.php'))) {
            $this->comment('Seeder already exists.');
        } else {
            $this->call('civi:make:seeder', [
                'name' => $name,
                '--path' => $this->option('path'),
                '--seed-db-init' => $this->option('seed-db-init'),
            ]);
        }
        // Generate the CiviDatabaseSeeder
        $name = 'CiviDatabaseSeeder';
        $this->info("Processing " . $name);
        if ($this->files->exists(database_path('seeds/' . $name . '.php'))) {
            $this->comment('Seeder already exists.');
        } else {
            $this->call('civi:make:seeder', [
                'name' => $name,
                '--path' => '',
                '--content' => implode("\n        // ", $this->seedTables),
            ]);
        }

        // Laravel installs a DatabaseSeeder file by default
        // - if it does not already have the CiviDatabaseSeeder run commeand, add it here.
        $name = 'DatabaseSeeder';
        $this->info("Processing " . $name);
        $dbSeeder = $this->files->get(database_path('seeds/' . $name . '.php'));
        if ($dbSeeder and (false === strpos($dbSeeder, '$this->call(CiviDatabaseSeeder::class);'))) {
            $dbSeeder = str_replace(
                "run()\n    {\n",
                "run()\n    {\n" . str_repeat(' ', 8) ."\$this->call(CiviDatabaseSeeder::class);\n",
                $dbSeeder
            );
            $this->files->put(database_path('seeds/' . $name . '.php'), $dbSeeder);
            $this->info("Updated seeder.");
            return;
        } elseif (!$dbSeeder) {
            $this->call('civi:make:seeder', [
                'name' => $name,
                '--path' => '',
                '--content' => str_repeat(' ', 8) ."\$this->call(CiviDatabaseSeeder::class);",
            ]);
        } else {
            $this->comment('Seeder already exists.');
        }
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0777, true, true);
        }
    }

    /**
     * Get the destination class path.
     *
     * @param  string $name
     * @return string
     */
    protected function getModelPath($name)
    {
        $name = str_replace($this->getAppNamespace(), '', $name);
        $name = ($this->option('model-path') ? $this->option('model-path') . '/': '') . $name;

        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
    }

    /**
     * Get the destination class path.
     *
     * @param  string $name
     * @return string
     */
    protected function getSeederPath($name)
    {
        return database_path('seeds/' . $this->option('path') . '/' . str_replace('\\', '/', $name) . '.php');
    }

    /**
     * Get the filename to store the migration.
     *
     * @param  string $name
     * @return string
     */
    protected function getFilename($name, $action)
    {
        return $this->path . '/' . date('Y_m_d_His') . '_' . $action .'_' . $name . '.php';
    }

    /**
     * Get the class name for the Eloquent model generator.
     *
     * @return string
     */
    protected function getModelName($table)
    {
        return ucwords(str_singular(camel_case($table['name'])));
    }

    /**
     * Compile the migration stub.
     *
     * @param array $table
     * @return string
     */
    protected function compileMigrationStub($table, $action)
    {
        $stub = $this->files->get(dirname(__DIR__) . '/Migrations/Stubs/migration.stub');

        $this->replaceClassName($stub, $table, $action)
            ->replaceSchema($stub, $table, $action)
            ->replaceTableName($stub, $table);

        return $stub;
    }

    /**
     * Replace the class name in the stub.
     *
     * @param  string $stub
     * @param array $table
     * @return $this
     */
    protected function replaceClassName(&$stub, $table, $action)
    {
        $className = title_case($action) . ucwords(camel_case($table['name']));
        $stub = str_replace('{{class}}', $className, $stub);

        return $this;
    }

    /**
     * Replace the schema for the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceSchema(&$stub, $table, $action = 'create')
    {
        $schema = (new SyntaxBuilder)->create($table, ['action' => $action]);

        $stub = str_replace(['{{schema_up}}', '{{schema_down}}'], $schema, $stub);

        return $this;
    }

    /**
     * Replace the table name in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceTableName(&$stub, $table)
    {
        $stub = str_replace('{{table}}', $table['name'], $stub);

        return $this;
    }

    /**
     * Get the application namespace.
     *
     * @return string
     */
    protected function getAppNamespace()
    {
        return Container::getInstance()->getNamespace();
    }

}
