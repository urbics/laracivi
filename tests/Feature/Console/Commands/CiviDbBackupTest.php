<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Urbics\Laracivi\Console\Commands\CiviDbBackup;
use Urbics\Laracivi\Console\Migrations\CiviDbBuilder;
use Illuminate\Database\DatabaseManager as DB;

class CiviDbBackupTest extends TestCase
{
    protected $testDbName = 'test_civicrm_db';
    protected $storage = 'app/civitest';

    /**
     * Tests the civi:db:backup command
     */
    public function testMakeDbSucceeds()
    {
        $application = new ConsoleApplication();

        $testedCommand = $this->app->make(CiviDbBackup::class);
        $testedCommand->setLaravel(app());
        $application->add($testedCommand);
        $command =  $application->find('civi:db:backup');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--database' => $this->testDbName,
            '--storage' => $this->storage,
        ]);
        $resultPath = storage_path($this->storage) . '/current_' . $this->testDbName . "_db.sql";
        $output = $commandTester->getDisplay();
        $length = filesize($resultPath);
        $this->cleanup();

        $this->assertContains("Backup of '" . $this->testDbName . "' created", $output);
        $this->assertNotEquals(0, $length);
    }

    public function setUp()
    {
        parent::setUp();
        $this->dbBuilder = $this->app->make(CiviDbBuilder::class);
        $result = $this->dbBuilder->build($this->testDbName);
        $this->assertContains('CiviCRM database created.', $result['status_message']);
    }

    public function cleanup()
    {
        resolve(DB::class)->connection(env('CIVI_DB_CONNECTION'))->statement("drop database " . $this->testDbName);
        $resultPath = storage_path($this->storage) . '/current_' . $this->testDbName . "_db.sql";
        if (file_exists($resultPath)) {
            unlink($resultPath);
        }
    }

}
