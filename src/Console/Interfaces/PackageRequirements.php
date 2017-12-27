<?php

namespace Urbics\Laracivi\Console\Interfaces;

interface PackageRequirements
{
    /**
     * Check the package requirements.
     *
     * @param  array  $params
     * @return array
     */
    public function checkRequirements(&$params = []);

}
