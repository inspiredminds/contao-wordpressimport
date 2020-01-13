<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

use Contao\System;

$GLOBALS['TL_DCA']['tl_news_archive']['palettes']['default'] .= ';{wordpress_import:hide},wpImport';
$GLOBALS['TL_DCA']['tl_news_archive']['palettes']['__selector__'][] = 'wpImport';
$GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport'] = 'wpImportUrl,wpImportCron,wpDefaultAuthor,wpImportAuthors,wpImportFolder';

$GLOBALS['TL_LANG']['tl_news_archive']['wordpress_import'] = 'WordPress Import';

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImport'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImport'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportUrl'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportUrl'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportFolder'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportFolder'],
    'exclude' => true,
    'inputType' => 'fileTree',
    'eval' => ['files' => false, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
    'sql' => 'binary(16) NULL',
];

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpDefaultAuthor'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpDefaultAuthor'],
    'exclude' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_user.name',
    'eval' => ['doNotCopy' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'hasOne', 'load' => 'eager'],
];

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportAuthors'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportAuthors'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportCron'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportCron'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];

if (\in_array('news_categories', array_keys(System::getContainer()->getParameter('kernel.bundles')), true)) {
    $GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport'] = str_replace(',wpImportFolder', ',wpImportFolder,wpImportCategory', $GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport']);
    $GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportCategory'] = [
        'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportCategory'],
        'exclude' => true,
        'inputType' => 'treePicker',
        'foreignKey' => 'tl_news_category.title',
        'eval' => ['multiple' => false, 'fieldType' => 'radio', 'foreignTable' => 'tl_news_category', 'titleField' => 'title', 'searchField' => 'title', 'managerHref' => 'do=news&table=tl_news_category'],
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ];
}

if (\in_array('ContaoCommentsBundle', array_keys(System::getContainer()->getParameter('kernel.bundles')), true)) {
    $GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport'] = str_replace(',wpImportAuthors', ',wpImportAuthors,wpImportComments', $GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport']);
    $GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportComments'] = [
        'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportComments'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50'],
        'sql' => "char(1) NOT NULL default ''",
    ];
}
