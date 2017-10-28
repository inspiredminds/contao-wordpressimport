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


$GLOBALS['TL_DCA']['tl_news']['fields']['wpPostId'] = array
(
	'label' => &$GLOBALS['TL_LANG']['tl_news']['wpPostId'],
	'sql' => "int(10) unsigned NULL"
);
