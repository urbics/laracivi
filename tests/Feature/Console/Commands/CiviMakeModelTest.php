<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Urbics\Laracivi\Console\Commands\CiviMakeModel;

class CiviMakeModelTest extends TestCase
{

    /**
     * Verifies civi:make:model generates the expected number of files.
     *
     * @return void
     */
    public function testModelGeneratesCorrectNumberOfFiles()
    {
        $targetFileCount = 1;
        $testDir = 'ModelsTempCiviTest';
        $filePath = app_path($testDir);
        if (!file_exists($filePath)) {
            mkdir($filePath);
        }

        $application = new ConsoleApplication();
        $testedCommand = $this->app->make(CiviMakeModel::class);
        $testedCommand->setLaravel(app());
        $application->add($testedCommand);
        $command =  $application->find('civi:make:model');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'name' => $testDir . '/TempCiviTest',
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
