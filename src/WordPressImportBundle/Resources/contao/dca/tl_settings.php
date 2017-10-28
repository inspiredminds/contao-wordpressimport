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
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'].= ';{wordpressimport_legend:hide},wpImportLimit';


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_settings']['fields']['wpImportLimit'] =  array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['wpImportLimit'],
	'inputType' => 'text',
	'eval'      => array('mandatory'=>true, 'rgxp'=>'natural', 'nospace'=>true, 'tl_class'=>'w50')
);
