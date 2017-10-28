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


use Contao\System;

$GLOBALS['TL_DCA']['tl_news_archive']['palettes']['default'].= ';{wordpress_import:hide},wpImport';
$GLOBALS['TL_DCA']['tl_news_archive']['palettes']['__selector__'][] = 'wpImport';
$GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport'] = 'wpImportUrl,wpImportCron,wpDefaultAuthor,wpImportAuthors,wpImportFolder';

$GLOBALS['TL_LANG']['tl_news_archive']['wordpress_import'] = 'WordPress Import';

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImport'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImport'],//['WordPress Import', 'Aktiviert den Import von einer WordPress Installation.'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => array('submitOnChange'=>true),
    'sql'       => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportUrl'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportUrl'],//['WordPress URL', 'URL zu der WordPress Installation.'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => array('mandatory'=>true, 'rgxp'=>'url', 'decodeEntities'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
    'sql'       => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportFolder'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportFolder'],//['Import Verzeichnis', 'Legt das Verzeichnis für den Import der Bilder fest.'],
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => array('files'=>false, 'fieldType'=>'radio', 'mandatory'=>true, 'tl_class'=>'clr'),
    'sql'       => "binary(16) NULL"
);

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpDefaultAuthor'] = array
(
    'label'      => &$GLOBALS['TL_LANG']['tl_news_archive']['wpDefaultAuthor'],//['Standard Autor', 'Der Standard Autor, falls keine Autoren importiert werden.'],
    'exclude'    => true,
    'inputType'  => 'select',
    'foreignKey' => 'tl_user.name',
    'eval'       => array('doNotCopy'=>true, 'chosen'=>true, 'mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type'=>'hasOne', 'load'=>'eager')
);

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportAuthors'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportAuthors'],//['Autoren importieren', 'Importiert die Autoren der WordPress Posts.'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => array('tl_class'=>'w50 m12'),
    'sql'       => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportCron'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportCron'],//['Periodisch importieren', 'Importiert WordPress Posts periodisch über den Contao Cronjob.'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => array('tl_class'=>'w50 m12'),
    'sql'       => "char(1) NOT NULL default ''"
);

if (in_array('news_categories', array_keys(System::getContainer()->getParameter('kernel.bundles'))))
{
    $GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport'] = str_replace(',wpImportFolder', ',wpImportFolder,wpImportCategory', $GLOBALS['TL_DCA']['tl_news_archive']['subpalettes']['wpImport']);
    $GLOBALS['TL_DCA']['tl_news_archive']['fields']['wpImportCategory'] = array
    (
        'label'      => &$GLOBALS['TL_LANG']['tl_news_archive']['wpImportCategory'],//['Kategorie', 'Optionale Wurzel-Kategorie.'],
        'exclude'    => true,
        'inputType'  => 'treePicker',
        'foreignKey' => 'tl_news_category.title',
        'eval'       => array('multiple'=>false, 'fieldType'=>'radio', 'foreignTable'=>'tl_news_category', 'titleField'=>'title', 'searchField'=>'title', 'managerHref'=>'do=news&table=tl_news_category'),
        'sql'        => "int(10) unsigned NOT NULL default '0'"
    );
}
