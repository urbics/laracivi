<?php
namespace Urbics\Laracivi\Console\Migrations;

use Illuminate\Support\Facades\Schema;
use Urbics\Laracivi\Console\Migrations\DbConfig;
use Urbics\Laracivi\Traits\StatusMessageTrait;
use Illuminate\Database\DatabaseManager as DB;

class CiviDbSeeder
{
    use StatusMessageTrait;
    
    protected $civiConfig;

    public function __construct(DbConfig $config)
    {
        $this->civiConfig = $config;
    }

    /**
     * Populates civicrm tables with default content from civicrm-core.
     * Optionally creates the civicrm tables.
     *
     * @return array
     */
    public function seed($createTables = false)
    {
        // Create (if requested) and seed tables.
        // TBD: Language-specific seeders (civicrm_data.en_US.mysql) should be used if present
        $conn = $this->civiConfig->connectionName();
        $sqlSrc = ['civicrm_data.mysql', 'civicrm_acl.mysql'];
        if ($createTables) {
            array_unshift($sqlSrc, 'civicrm.mysql');
        }
        foreach ($sqlSrc as $src) {
            $queries = preg_split('/;\s*$/m', $this->cleanSql($src));
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    resolve(DB::class)->connection($conn)->statement($query);
                }
            }
        }
        return [
            'status_code' => $this->getSuccessStatusCode(),
            'status_message' => "CiviCRM tables were seeded.",
        ];
    }

    /**
     * Cleans the SQL in the civicrm*.mysql files
     *
     * @param  string $src
     * @return string
     */
    protected function cleanSql($src)
    {
        $sqlRaw = file_get_contents($this->civiConfig->sqlPath() . '/' . $src);
        // Source: civicrm-core/install/civicrm.php
        // change \r\n to fix windows issues
        $sqlRaw = str_replace("\r\n", "\n", $sqlRaw);
        //get rid of comments starting with # and --
        $sqlRaw = preg_replace("/^#[^\n]*$/m", "\n", $sqlRaw);
        $sqlRaw = preg_replace("/^(--[^-]).*/m", "\n", $sqlRaw);
        // Clean up a comment that is missed by the above
        $target = '
/*******************************************************
*
* civicrm_county
*
*******************************************************/';
        $sqlRaw = str_replace($target, '', $sqlRaw);
        
        return $sqlRaw;
    }

}
