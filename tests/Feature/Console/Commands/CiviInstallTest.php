<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Urbics\Laracivi\Console\Commands\CiviInstall;

class CiviInstallTest extends TestCase
{
    /**
     * Tests the civi:install command
     */
    public function testCommandSucceeds()
    {
        $application = new ConsoleApplication();

        $testedCommand = $this->app->make(CiviInstall::class);
        $testedCommand->setLaravel(app());
        $application->add($testedCommand);
        $command =  $application->find('civi:install');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $output = $commandTester->getDisplay();

        $this->assertContains('CiviCRM is installed', $output);
    }

}
