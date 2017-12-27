<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Urbics\Laracivi\Console\Commands\CiviMakeSeeder;

class CiviMakeSeederTest extends TestCase
{

    /**
     * Verifies civi:make:seeder generates the expected number of files.
     *
     * @return void
     */
    public function testSeederGeneratesCorrectNumberOfFiles()
    {
        $targetFileCount = 1;
        $testDir = 'TempCiviTest';
        $filePath = database_path("seeds/$testDir");

        $application = new ConsoleApplication();
        $testedCommand = $this->app->make(CiviMakeSeeder::class);
        $testedCommand->setLaravel(app());
        $application->add($testedCommand);
        $command =  $application->find('civi:make:seeder');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'name' => $testDir . 'Seeder',
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

        $this->assertEquals($targetFileCount, $fileCount);
    }

}
