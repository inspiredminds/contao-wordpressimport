<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

namespace WordPressImportBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WordPressImportBundle\Service\Importer;

class ImportCommand extends Command
{
    protected static $defaultName = 'wordpressimport';

    private $importer;

    public function __construct(Importer $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    protected function configure(): void
    {
        $this
             ->setDescription('Imports all WordPress posts for configured news archives.')
             ->addArgument('limit', InputArgument::OPTIONAL, 'Limit the import to the defined number of items at a time.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting WordPress import'.($input->getArgument('limit') ? ' with limit: '.$input->getArgument('limit') : ''));

        $result = $this->importer->import($input->getArgument('limit'));

        $output->writeln('Imported '.\count($result).' WordPress posts.');
        
        return 0;
    }
}
