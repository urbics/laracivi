# Laracivi - a Laravel 5.5 to CiviCRM bridge

Installs CiviCRM as a package within a laravel project.  

Provides civicrm_api3 access to an existing or a new CiviCRM database using a thin api wrapper.  Includes console commands to build migrations for all CiviCRM tables from CiviCRM's schema.xml source and seeders from civicrm_data.mysql and civicrm_acl.mysql source, as well as Entity model classes for all CiviCRM tables.  Also includes console commands to generate a CiviCRM database directly from civicrm.mysql, and to backup and restore a CiviCRM database.  The package uses a lightly modified fork of civicrm-core and unmodified civicrm/civrm-packages.

## Package Installation
```sh
composer require urbics/laracivi
```
Or manually by modifying `composer.json` file:
``` json
"require": {
    "urbics/laracivi": "~1.*"
}
```

Next, add package repository sources to the root composer.json in your project (these components are not currently on packagist):
``` json
"repositories": [
    {
      "type": "git",
      "url": "https://github.com/urbics/civicrm-core.git"
    },
    {
      "type": "git",
      "url": "https://github.com/civicrm/zetacomponents-mail.git"
    },
    {
      "type": "git",
      "url": "https://github.com/totten/topsort.php.git"
    },
    {
       "type": "package",
        "package": {
            "name": "civicrm/civicrm-packages",
            "version": "master",
            "source": {
                "url": "https://github.com/civicrm/civicrm-packages",
                "type": "git",
                "reference": "master"
            }
        }
    }
],
```
And run `composer install`

## CiviCRM Installation
From your project directory, run

`php artisan civi:install`

(If you are using homestead or another virtual machine, ssh into the virtual machine before running this)

 which will:
 - Add a civicrm.settings.php file to `vendor/civicrm/civicrm-core/src`
 - Move civicrm-packages to `vendor/civicrm/civicrm-core/packages`
 - Generate civicrm.mysql and related files in `vendor/civicrm/civicrm-core/sql` from `vendor/civicrm/civicrm-core/xml/schema/Schema.xml` source
 - Add several `CIVI_XXX` settings to the bottom of your project's .env file  

then run
`php artisan vendor:publish`
to bring a civi.php settings file into the config folder.

## Next Steps

 - Run civi:make:db to create a new civicrm database directly, using CiviCRM's civicrm.msql script.
 - Or, run civi:make:migration to generate migration files, optionally with seeder and model classes.  
 - Build the tables using Laravel's migration: `php artisan migrate --database=civicrm --path=database/migrations/civi --seed` (These are the default settings - change database connection and path as needed)

## Tests
The project includes phpunit tests for each of the console commands as well as for basic api functionality.