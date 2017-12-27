<?php

namespace Urbics\Laracivi\Console\Commands;

use Illuminate\Console\Command;
use Urbics\Laracivi\Console\Migrations\CodeGen;
use Urbics\Laracivi\Console\Interfaces\PackageEnvironment;
use Urbics\Laracivi\Console\Interfaces\PackageRequirements;
use Urbics\Laracivi\Traits\StatusMessageTrait;

class CiviInstall extends Command
{
    use StatusMessageTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'civi:install
        {civicrm-core=civicrm/civicrm-core : name of civicrm-core package in vendor directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Installs CiviCRM.';

    /**
     * Environment class for CiviCRM
     *
     * @var PackageEnvironment
     */
    protected $env;

    /**
     * Requirements class for CiviCRM
     *
     * @var PackageRequirements
     */
    protected $req;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(PackageEnvironment $env, PackageRequirements $req)
    {
        parent::__construct();

        $this->env = $env;
        $this->req = $req;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->failed(($result = $this->installCivi()))) {
            return $this->error($result['status_message']);
        }
        if (!empty($result['status_message'])) {
            return $this->info($result['status_message']);
        }
    }

    /**
     * Move civicrm-packages into civicrm-core.
     * Generate civicrm.mysql scripts and DAO objects.
     *
     * @return array
     */
    protected function installCivi()
    {
        $params = [
            'civicrm_core' => ($this->argument('civicrm-core')
                ?: (config('civi.package')
                    ?: (env('CIVI_CORE_PACKAGE')
                        ?: 'civicrm-core'))),
            'civicrm_packages' => 'civicrm/civicrm-packages',
            'civicrm_settings' => '',
        ];
        if ($this->failed(($result = $this->req->checkRequirements($params)))) {
            return $result;
        }
        $this->env->setEnvironment($params)
            ->generateSettings()
            ->setPaths()
            ->setMemoryLimit();
        $this->installPackages($params);
        $this->generateCode($params);

        return ['status_code' => $this->getSuccessStatusCode(), 'status_message' => "CiviCRM is installed."];
    }

    /**
     * Moves civicrm-packages into 'packages' directory of civicrm-core package.
     *
     * @param  string $packageDir
     * @return null
     */
    public function installPackages($params)
    {
        $corePackagesDir = base_path('vendor/' . $params['civicrm_core'] . '/packages');
        $civiPackageDir =  base_path('vendor/' . $params['civicrm_packages']);
        if (file_exists($civiPackageDir) and (! file_exists($corePackagesDir))) {
            rename($civiPackageDir, $corePackagesDir);
        }
    }

    /**
     * Generate civicrm.mysql, civicrm_data.mysql and civicrm_acl.mysql files.
     *
     * @return null
     */
    public function generateCode($params)
    {
        if (! file_exists(base_path('vendor/' . $params['civicrm_core'] . '/sql/civicrm.mysql'))) {
            (new CodeGen())->generate($params['civicrm_core']);
        }
    }
}
