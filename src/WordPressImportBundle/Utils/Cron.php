<?php

/**
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 *
 * @package   WordPressImportBundle
 * @author    Fritz Michael Gschwantner <https://github.com/fritzmg>
 * @license   LGPL-3.0+
 * @copyright inspiredminds 2017
 */


namespace WordPressImportBundle\Utils;

use Contao\Config;
use Contao\System;

/**
 * Utility class for Contao Cronjob Hooks.
 */
class Cron
{
    /**
     * Triggers the import via the Contao Cronjob.
     * @return void
     */
    public function import()
    {
        System::getContainer()->get('wordpressimporter')->import(Config::get('wpImportLimit'), true);
    }
}
