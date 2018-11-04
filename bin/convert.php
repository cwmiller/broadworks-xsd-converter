#!/usr/bin/env php
<?php

use CWM\BroadWorksXsdConverter\Parser;
use CWM\BroadWorksXsdConverter\ModelWriter;
use CWM\BroadWorksXsdConverter\TraitWriter;

if (is_file(__DIR__ . '/../../../autoload.php')) {
    require __DIR__ . '/../../../autoload.php';
} else {
    require __DIR__ . '/../vendor/autoload.php';
}

$args = $argv;
$opts = [];

$args = array_values(array_filter($argv, function($arg) {
    return $arg[0] !== '-';
}));

$opts = array_values(array_filter($argv, function($arg) {
    return $arg[0] === '-';
}));

if (count($args) < 6) {
    echo sprintf('Usage: %s [-d] path-to-root-xsd output-directory models-namespace traits-namespace nil-classname', basename($args[0])) . PHP_EOL;
    exit(-1);
}

list($_, $rootXsd, $outputDirectory, $modelNamespace, $traitNamespace, $nilClassname) = $args;

$debug = in_array('-d', $opts, true);

$parser = new Parser($rootXsd, $debug);
$types = $parser->parse();

$modelWriter = new ModelWriter($outputDirectory, $modelNamespace, $nilClassname, $debug);
$modelWriter->write($types);

$traitWriter = new TraitWriter($outputDirectory, $modelNamespace, $traitNamespace, $debug);
$traitWriter->write($types);