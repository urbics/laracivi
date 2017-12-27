<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Urbics\Laracivi\Console\Commands\CiviMakeMigration;

class CiviMakeMigrationTest extends TestCase
{

    /**
     * Verifies civi:make:migration generates the expected number of files.
     *
     * @return void
     */
    public function testMigrationGeneratesCorrectNumberOfFiles()
    {
        $targetFileCount = 304;
        $testDir = 'TempCiviTest';
        $filePath = database_path("migrations/$testDir");

        $application = new ConsoleApplication();
        $testedCommand = $this->app->make(CiviMakeMigration::class);
        $testedCommand->setLaravel(app());
        $application->add($testedCommand);
        $command =  $application->find('civi:make:migration');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--path' => $testDir,
        ]);
        $fileCount = 0;
        $dir = new \DirectoryIterator($filePath);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $fileCount++;
            }
        }

        // Remove directories before assertion
        if (file_exists($filePath)) {
            array_map('unlink', glob("$filePath/*.php"));
            rmdir($filePath);
        }

        // CiviCRM currently (late 2017) has 152 tables.
        // Migration generates a foreign key migration for each, so 304 files should exist in the migration folder.
        $this->assertEquals($targetFileCount, $fileCount);
    }

}
