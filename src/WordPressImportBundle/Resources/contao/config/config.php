<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

/**
 * Register Cronjobs.
 */
$GLOBALS['TL_CRON']['hourly'][] = ['wordpressimport_cron', 'import'];

/*
 * Default config
 */
$GLOBALS['TL_CONFIG']['wpImportLimit'] = 10;
