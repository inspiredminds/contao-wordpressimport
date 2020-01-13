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
use WordPressImportBundle\Service\Importer;

/**
 * Utility class for Contao Cronjob Hooks.
 */
class Cron
{
    private $importer;

    public function __construct(Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
     * Triggers the import via the Contao Cronjob.
     */
    public function import(): void
    {
        try {
            $this->importer->import(Config::get('wpImportLimit'), true);
        } catch (\Exception $e) {
            System::log('An error occurred while importing WordPress posts: '.$e->getMessage(), __METHOD__, TL_ERROR);
        }
    }
}
