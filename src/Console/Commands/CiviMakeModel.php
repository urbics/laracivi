<?php

namespace Urbics\Laracivi\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class CiviMakeModel extends GeneratorCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'civi:make:model
        {name : The name of the class}';
    
    protected $name = 'civi:make:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new database model classes.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return dirname(__DIR__) . '/Migrations/Stubs/model.stub';
    }
}
