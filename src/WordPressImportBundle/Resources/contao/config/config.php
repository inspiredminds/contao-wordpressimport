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


/**
 * Register Cronjobs
 */
$GLOBALS['TL_CRON']['hourly'][] = array('WordPressImportBundle\Utils\Cron', 'import');


/**
 * Default config
 */
$GLOBALS['TL_CONFIG']['wpImportLimit'] = 10;
