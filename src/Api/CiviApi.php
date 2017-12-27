<?php
namespace  Urbics\Laracivi\Api;

class CiviApi extends \civicrm_api3
{
    public function api()
    {
        // $this->civiApi = new \civicrm_api3(['conf_path' => env('CIVI_SETTINGS' . '/src/civicrm.settings.php')]);
        $this->civiApi = new \civicrm_api3();

        return $this->civiApi;
    }
    
}