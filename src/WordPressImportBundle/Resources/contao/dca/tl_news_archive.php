<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

use Codefog\NewsCategoriesBundle\CodefogNewsCategoriesBundle;
use Contao\CommentsBundle\ContaoCommentsBundle;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

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

if (class_exists(CodefogNewsCategoriesBundle::class)) {
    $GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportCategory'] = [
        'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportCategory'],
        'exclude' => true,
        'inputType' => 'newsCategoriesPicker',
        'foreignKey' => 'tl_news_category.title',
        'eval' => ['multiple' => false, 'fieldType' => 'radio', 'foreignTable' => 'tl_news_category', 'titleField' => 'title', 'searchField' => 'title', 'managerHref' => 'do=news&table=tl_news_category'],
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ];

    PaletteManipulator::create()
        ->addField('wpImportCategory', 'wpImportFolder', PaletteManipulator::POSITION_AFTER)
        ->applyToSubpalette('wpImport', 'tl_news_archive')
    ;
}

if (class_exists(ContaoCommentsBundle::class)) {
    $GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportComments'] = [
        'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportComments'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50'],
        'sql' => "char(1) NOT NULL default ''",
    ];

    PaletteManipulator::create()
        ->addField('wpImportComments', 'wpImportAuthors', PaletteManipulator::POSITION_AFTER)
        ->applyToSubpalette('wpImport', 'tl_news_archive')
    ;
}
