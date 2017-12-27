<?php

namespace Urbics\Laracivi\Console\Installers;

use Urbics\Laracivi\Console\Interfaces\PackageRequirements;
use Urbics\Laracivi\Traits\StatusMessageTrait;

class Requirements implements PackageRequirements
{
    use StatusMessageTrait;

    /**
     * Checks package requirements are met.
     *
     * @param  array $params
     * @return array
     */
    public function checkRequirements(&$params = [])
    {
        $params = array_merge(['civicrm_core' => '', 'civicrm_packages' => 'civicrm/civicrm-packages'], $params);
        if (!($params['civicrm_core'] = $this->hasPackage($params['civicrm_core']))) {
            return ['status_code' => $this->getFailStatusCode(),
                'status_message' => "Unable to find the civicrm-core package '" . $params['civicrm_core'] . "'.\nIf it is named something other than '[vendor]/civicrm-core', include the package name with civi:install.\nAlso check composer.json to confirm the package is actually installed."
            ];
        }
        if (!($params['civicrm_packages'] = $this->hasPackage($params['civicrm_packages']))) {
            return ['status_code' => $this->getFailStatusCode(),
                'status_message' => "Unable to find the civicrm/civicrm-package.\nPlease use composer require to add it."
            ];
        }

        return ['status_code' => $this->getSuccessStatusCode(),'status_message' => "",];
    }

    protected function hasPackage($name)
    {
        $file = base_path().'/composer.lock';
        $packages = json_decode(file_get_contents($file), true)['packages'];
        foreach ($packages as $package) {
            if (false !== strpos($package['name'], $name)) {
                return $package['name'];
            }
        }
        
        return null;
    }

}
