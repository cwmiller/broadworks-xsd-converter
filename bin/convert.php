#!/usr/bin/env php
<?php

use CWM\BroadWorksXsdConverter\Parser;
use CWM\BroadWorksXsdConverter\Writer;

require __DIR__ . '/../vendor/autoload.php';

if ($argc < 4) {
    echo sprintf('Usage: %s path-to-root-xsd output-directory root-namespace', basename($argv[0])) . PHP_EOL;
    exit(-1);
}

$rootXsd = $argv[1];
$outputDirectory = $argv[2];
$rootNamespace = $argv[3];

$parser = new Parser($rootXsd, false);
$types = $parser->parse();

$writer = new Writer($outputDirectory, $rootNamespace);
$writer->write($types);