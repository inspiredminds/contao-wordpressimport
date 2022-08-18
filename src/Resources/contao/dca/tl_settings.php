<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

/*
 * Palettes.
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{wordpressimport_legend:hide},wpImportLimit';

/*
 * Fields
 */
$GLOBALS['TL_DCA']['tl_settings']['fields']['wpImportLimit'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['wpImportLimit'],
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50'],
];
