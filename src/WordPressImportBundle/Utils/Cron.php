<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
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
     */
    public function import(): void
    {
        try {
            System::getContainer()->get('wordpressimporter')->import(Config::get('wpImportLimit'), true);
        } catch (\Exception $e) {
            System::log('An error occurred while importing WordPress posts: '.$e->getMessage(), __METHOD__, TL_ERROR);
        }
    }
}
