<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Urbics\Laracivi\Api\CiviApi;

class CiviApiTest extends TestCase
{
    protected $testDbName = 'test_civicrm_db';

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetCountry()
    {
        $api = new CiviApi();
        $apiParams = array(
            'name' => 'Mexico',
        );
        $api->Country->Get($apiParams);
        $this->assertEquals(1, $api->count);
    }
}
