<?php
namespace Urbics\Laracivi\Console\Migrations;

class CodeGen
{
    /**
     * Generate civicrm.mysql and other sql scripts in civicrm-core/sql.
     * Adopted from civicrm_core/xml/GenCode.php.
     *
     * @return null
     */
    public function generate($civiPackage)
    {
        $civiPath = base_path('vendor/' . $civiPackage);
        require_once($civiPath . '/CRM/Core/ClassLoader.php');
        \CRM_Core_ClassLoader::singleton()->register();

        /* To avoid running CRM_Core_CodeGen_Main from the civicrm-core/xml directory, these changes
            need to be made to CRM_Core_CodeGen_I18n:
            1. line 16: file_get_contents('templates/languages.tpl', true)
            2. line 21: file_put_contents(base_path('vendor/civicrm/civicrm-core/') .'install/langs.php'
        */
        $cwd = getcwd();
        chdir($civiPath . '/xml');

        $genCode = new \CRM_Core_CodeGen_Main(
            $civiPath . '/CRM/Core/DAO/', // $CoreDAOCodePath
            $civiPath . '/sql/', // $sqlCodePath
            $civiPath . '/', // $phpCodePath
            $civiPath . '/templates/', // $tplCodePath
            null, // IGNORE
            'NoCms', // framework - requires the NoCms classes included in dmealy/civicrm-core package.
            null, // db version
            $civiPath . '/xml/schema/Schema.xml', // schema file
            null  // path to digest file
        );
        $genCode->main();
        chdir($cwd);
    }
}
