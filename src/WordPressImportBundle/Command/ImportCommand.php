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


namespace WordPressImportBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
    	$this->setName('wordpressimport')
    	     ->setDescription('Imports all WordPress posts for configured news archives.')
    	     ->addArgument('limit', InputArgument::OPTIONAL, 'Limit the import to the defined number of items at a time.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
	    $output->writeln('Starting WordPress import' . ($input->getArgument('limit') ? ' with limit: ' . $input->getArgument('limit') : ''));

        $importer = $this->getContainer()->get('wordpressimporter');
        $result = $importer->import($input->getArgument('limit'));

        $output->writeln('Imported ' . count($result) . ' WordPress posts.');
    }
}
