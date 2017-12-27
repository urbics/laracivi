<?php

namespace Urbics\Laracivi\Console\Installers;

use Urbics\Laracivi\Console\Interfaces\PackageEnvironment;

class Environment implements PackageEnvironment
{
    /**
     * Adds CiviCRM values to the .env file.
     *
     * @param array $params
     * @return   $this
     */
    public function setEnvironment($params)
    {
        $oldEnv = $this->readEnvironment();
        $params = [
            'CIVI_CMS' => 'NoCms',
            'CIVI_CORE_PACKAGE' => $params['civicrm_core'],
            'CIVI_SETTINGS' => ($params['civicrm_settings'] ?: $params['civicrm_core']),
            'CIVI_DB_CONNECTION' => 'civicrm',
            'CIVI_DB_DATABASE' => 'civicrm',
            'CIVI_DB_HOST' => '127.0.0.1',
            'CIVI_DB_PORT' => '3306',
            'CIVI_DB_USERNAME' => (isset($oldEnv['DB_USERNAME']) ? $oldEnv['DB_USERNAME'] : "civiuser"),
            'CIVI_DB_PASSWORD' => 'secret',
        ];
        $addEnv = '';
        foreach ($params as $key => $value) {
            if (empty($oldEnv[$key])) {
                $addEnv .= $key ."=" . $value . "\n";
            }
        }
        $this->appendEnvironment($addEnv);

        return $this;
    }

    /**
     * Retrieve values from .env as array.
     *
     * @return array
     */
    private function readEnvironment()
    {
        $env = preg_split('/\s+/', file_get_contents(base_path('.env')));
        $oldEnv = [];
        foreach ($env as $value) {
            $val = explode("=", $value, 2);
            if (!empty($val[0])) {
                $oldEnv[$val[0]] = (empty($val[1]) ? '' : $val[1]);
            }
        }

        return $oldEnv;
    }

    /**
     * Append the CiviCRM environment values to .env.
     *
     * @param  string $addEnv
     * @return null
     */
    private function appendEnvironment($addEnv)
    {
        if (!empty($addEnv)) {
            $addEnv = "\n" . $addEnv;
            $oldEnv = file_get_contents(base_path('.env'));
            $newEnv = $oldEnv . $addEnv;
            file_put_contents(base_path('.env'), $newEnv);
        }
    }

    /**
     * Set CiviCRM paths
     *
     * @return   $this
     */
    public function setPaths()
    {
        if (!($civiCore = env('CIVI_CORE_PACKAGE'))) {
            $curEnv = $this->readEnvironment();
            $civiCore = (empty($curEnv['CIVI_CORE_PACKAGE']) ? '' : $curEnv['CIVI_CORE_PACKAGE']);
        }

        date_default_timezone_set('UTC'); // avoid php warnings if timezone is not set - CRM-10844
        defined('CIVICRM_UF') or define('CIVICRM_UF', 'NoCms');  // Disregarded.
        defined('CIVICRM_UF_BASEURL') or define('CIVICRM_UF_BASEURL', '/');
        ini_set(
            'include_path',
            get_include_path()
            . PATH_SEPARATOR . base_path('vendor/' . $civiCore)
            . PATH_SEPARATOR . base_path('vendor/' . $civiCore . '/xml')
        );

        return $this;
    }

    /**
     * CiviCRM requires that the memory_limit is at least 512 MB.
     *
     * @return   $this
     */
    public function setMemoryLimit()
    {
        $memLimitString = trim(ini_get('memory_limit'));
        $memLimitUnit = strtolower(substr($memLimitString, -1));
        $memLimit = (int) $memLimitString;
        switch ($memLimitUnit) {
            case 'g':
                $memLimit *= 1024;
                break;
            case 'm':
                $memLimit *= 1024;
                break;
            case 'k':
                $memLimit *= 1024;
                break;
        }
        if ($memLimit >= 0 and $memLimit < 536870912) {
            // Note: When processing all locales, CRM_Core_I18n::singleton() eats a lot of RAM.
            ini_set('memory_limit', -1);
        }

        return $this;
    }

    /**
     * Generate the civicrm.settings.php file.
     *
     * @return $this
     */
    public function generateSettings()
    {
        // During install the .env values created in setEnvironment have not yet been loaded into memory.
        $curEnv = $this->readEnvironment();
        $crmDir = base_path('vendor/' . $curEnv['CIVI_CORE_PACKAGE']);
        $settings = ($curEnv['CIVI_SETTINGS'] ?: $curEnv['CIVI_CORE_PACKAGE']);
        $settingsDir = base_path('vendor/' . $settings);
        $tplPath = $crmDir . '/templates/CRM/common';
        $configFile = $settingsDir . '/src/civicrm.settings.php';
        $compileDir = $crmDir . '/templates_c';
        $configLogDir = $crmDir . '/ConfigAndLog';
        if (!file_exists($compileDir)) {
            mkdir($compileDir);
        }
        if (!file_exists($settingsDir . '/src/')) {
            mkdir($settingsDir . '/src/');
        }
        if (!file_exists($configLogDir)) {
            mkdir($configLogDir);
        }
        if (file_exists($configFile)) {
            return $this;
        }
        $dbUser = $curEnv['CIVI_DB_USERNAME'];
        $dbHost = $curEnv['CIVI_DB_HOST'];
        $dbName = $curEnv['CIVI_DB_DATABASE'];
        $dbPass = $curEnv['CIVI_DB_PASSWORD'];
        $params = [
            'crmRoot' => $crmDir,
            'templateCompileDir' => addslashes($compileDir),
            'frontEnd' => 0,
            'dbUser' => addslashes($dbUser),
            'dbPass' => addslashes($dbPass),
            'dbHost' => $dbHost,
            'dbName' => addslashes($dbName),
            'baseURL' => $curEnv['APP_URL'],
            'cms'   => $curEnv['CIVI_CMS'],
            'CMSdbUser' => addslashes($dbUser),
            'CMSdbPass' => addslashes($dbPass),
            'CMSdbHost' => $dbHost,
            'CMSdbName' => addslashes($dbName),
            'siteKey' => $curEnv['APP_KEY'],
        ];
        $tplRaw = file_get_contents($tplPath . '/civicrm.settings.php.template');
        foreach ($params as $key => $value) {
            $tplRaw = str_replace('%%' . $key . '%%', $value, $tplRaw);
        }
        file_put_contents($configFile, $tplRaw);

        return $this;
    }

    /**
     * Initialize paths for CiviCRM.
     *
     * @return null
     */
    public function boot()
    {
        $settingsDir = base_path('vendor/' . env('CIVI_SETTINGS')) . '/src/civicrm.settings.php';
        if (file_exists($settingsDir)) {
            $this->setPaths();
            $this->setMemoryLimit();
            require_once($settingsDir);
            require_once('api/class.api.php');
        }
    }

}
