<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Urbics\Laracivi\Console\Commands\CiviMakeDb;
use Illuminate\Database\DatabaseManager as LaraDb;

class CiviMakeDbTest extends TestCase
{

    /**
     * Tests the civi:install command
     */
    public function testMakeDbSucceeds()
    {
        $testDbName = 'test_civicrm_db';
        $application = new ConsoleApplication();

        $testedCommand = $this->app->make(CiviMakeDb::class);
        $testedCommand->setLaravel(app());
        $application->add($testedCommand);
        $command =  $application->find('civi:make:db');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'name' => $testDbName,
        ]);
        $output = $commandTester->getDisplay();
        // Remove test database
        $db = resolve(LaraDb::class);
        $db->connection(env('CIVI_DB_CONNECTION'))->statement("drop database $testDbName");

        $this->assertContains('CiviCRM database created.', $output);
    }

}
