#!/usr/bin/env php
<?php

use CWM\BroadWorksXsdConverter\Parser;
use CWM\BroadWorksXsdConverter\PHP\ModelWriter;
use CWM\BroadWorksXsdConverter\PHP\TraitWriter;

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

if (count($args) < 8) {
    echo sprintf('Usage: %s [-d] path-to-root-xsd output-directory models-namespace traits-namespace nil-classname errorresponse-classname validation-classname', basename($args[0])) . PHP_EOL;
    exit(-1);
}

list($_, $rootXsd, $outputDirectory, $modelNamespace, $traitNamespace, $nilClassname, $errorResponseClassname, $validationClassname) = $args;

$debug = in_array('-d', $opts, true);

$parser = new Parser($rootXsd, $debug);
$types = $parser->parse();

$modelWriter = new ModelWriter($outputDirectory, $modelNamespace, $nilClassname, $debug);
$modelWriter->write($types);

$traitWriter = new TraitWriter($outputDirectory, $modelNamespace, $traitNamespace, $errorResponseClassname, $validationClassname, $debug);
$traitWriter->write($types);