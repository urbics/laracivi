<?php

namespace Tests\Unit\Installers;

use Tests\TestCase;
use Urbics\Laracivi\Console\Installers\Requirements;

class RequirementsTest extends TestCase
{
    public function testFailsIfCiviCorePackageNotFound()
    {
        $params = [
            'civicrm_core' => 'fake_package_name',
        ];
        $req = new Requirements();
        $output = $req->checkRequirements($params);

        $this->assertEquals('404', $output['status_code']);
    }

    public function testSucceedsIfCiviPackagesFound()
    {
        $params = [
            'civicrm_core' => (config('civi.package')
                    ?: (env('CIVI_CORE_PACKAGE')
                        ?: 'civicrm-core')),
            'civicrm_packages' => 'civicrm/civicrm-packages',
        ];
        $req = new Requirements();
        $output = $req->checkRequirements($params);

        $this->assertEquals('200', $output['status_code']);
    }
}
