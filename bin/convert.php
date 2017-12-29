#!/usr/bin/env php
<?php

use CWM\BroadWorksXsdConverter\Parser;
use CWM\BroadWorksXsdConverter\Writer;

require __DIR__ . '/../vendor/autoload.php';

$args = $argv;
$opts = [];

$args = array_values(array_filter($argv, function($arg) {
    return $arg[0] !== '-';
}));

$opts = array_values(array_filter($argv, function($arg) {
    return $arg[0] === '-';
}));

if (count($args) < 4) {
    echo sprintf('Usage: %s [-d] path-to-root-xsd output-directory root-namespace', basename($args[0])) . PHP_EOL;
    exit(-1);
}

$rootXsd = $args[1];
$outputDirectory = $args[2];
$rootNamespace = $args[3];
$debug = in_array('-d', $opts, true);

$parser = new Parser($rootXsd, $debug);
$types = $parser->parse();

$writer = new Writer($outputDirectory, $rootNamespace);
$writer->write($types);