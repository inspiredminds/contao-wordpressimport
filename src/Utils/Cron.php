<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

namespace WordPressImportBundle\Utils;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Psr\Log\LoggerInterface;
use WordPressImportBundle\Service\Importer;

/**
 * Utility class for Contao Cronjob Hooks.
 */
class Cron
{
    private $importer;
    private $logger;
    private $framework;

    public function __construct(Importer $importer, LoggerInterface $logger, ContaoFramework $framework)
    {
        $this->importer = $importer;
        $this->logger = $logger;
        $this->framework = $framework;
    }

    /**
     * Triggers the import via the Contao Cronjob.
     *
     * @CronJob("hourly")
     */
    public function import(): void
    {
        $this->framework->initialize();

        try {
            $this->importer->import(Config::get('wpImportLimit'), true);
        } catch (\Exception $e) {
            $this->logger->info('An error occurred while importing WordPress posts: '.$e->getMessage(), ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
        }
    }
}
