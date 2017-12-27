<?php

namespace Urbics\Laracivi\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class CiviMakeSeeder extends GeneratorCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'civi:make:seeder
        {name : The name of the class}
        {--path=civi : Path to seed files under database/seed}
        {--content=//  : Content to be inserted}
        {--seed-db-init=CiviDefault : Seeder class containing the CiviCRM-generated seeders.}';
    
    protected $name = 'civi:make:seeder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new database seed classes.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Seeder';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return dirname(__DIR__) . '/Migrations/Stubs/seed.stub';
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        return database_path('seeds/' . $this->option('path') . '/' . $name.'.php');
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        return $this->addSeedCommand($name, $stub);
    }

    protected function addSeedCommand($name, $stub)
    {
        if ($name == $this->option('seed-db-init')) {
            $stub = str_replace('{{use_classes}}', "use Urbics\Laracivi\Console\Migrations\CiviDbSeeder;\n", $stub);
            $constructor = str_repeat(' ', 4) . "protected \$dbSeeder;\n\n";
            $constructor .= str_repeat(' ', 4) . "public function __construct(CiviDbSeeder \$dbSeeder)\n";
            $constructor .= str_repeat(' ', 4) . "{\n";
            $constructor .= str_repeat(' ', 8) . "\$this->dbSeeder = \$dbSeeder;\n";
            $constructor .= str_repeat(' ', 4) . "}\n";
            $stub = str_replace('{{constructor}}', $constructor, $stub);
            $stub = str_replace('{{seeder}}', '$this->dbSeeder->seed();', $stub);

            return $stub;
        }
        $stub = str_replace('{{use_classes}}', "", $stub);
        $stub = str_replace('{{constructor}}', "", $stub);
        $content = $this->option('content') .  ($this->option('content') == '//' ? ' ' : '');
        $stub = str_replace('{{seeder}}', $content, $stub);

        return $stub;
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        return $name;
    }
}
