<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

$GLOBALS['TL_DCA']['tl_news']['fields']['wpPostId'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news']['wpPostId'],
    'sql' => 'int(10) unsigned NULL',
];
