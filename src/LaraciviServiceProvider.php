<?php

namespace Urbics\Laracivi;

use Illuminate\Support\ServiceProvider;
use Urbics\Laracivi\Console\Installers\Environment;

class LaraciviServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/Console/Installers/civi.php' => config_path('civi.php'),
        ]);

        (new Environment())->boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\CiviDbBackup::class,
                Console\Commands\CiviInstall::class,
                Console\Commands\CiviMakeDb::class,
                Console\Commands\CiviMakeMigration::class,
                Console\Commands\CiviMakeModel::class,
                Console\Commands\CiviMakeSeeder::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'Urbics\Laracivi\Console\Interfaces\PackageEnvironment',
            'Urbics\Laracivi\Console\Installers\Environment'
        );
        $this->app->bind(
            'Urbics\Laracivi\Console\Interfaces\PackageRequirements',
            'Urbics\Laracivi\Console\Installers\Requirements'
        );
    }
}
