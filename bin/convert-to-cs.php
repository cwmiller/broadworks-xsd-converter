#!/usr/bin/env php
<?php

use CWM\BroadWorksXsdConverter\CS\ExtensionsWriter;
use CWM\BroadWorksXsdConverter\CS\ModelWriter;
use CWM\BroadWorksXsdConverter\Parser;

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

if (count($args) < 7) {
    echo sprintf('Usage: %s [-d] path-to-root-xsd output-directory models-namespace extensions-namespace error-response-exception-class validation-namespace', basename($args[0])) . PHP_EOL;
    exit(-1);
}

list($_, $rootXsd, $outputDirectory, $modelNamespace, $extensionsNamespace, $errorResponseName, $validationNamespace) = $args;

$debug = in_array('-d', $opts, true);

$parser = new Parser($rootXsd, $debug);
$types = $parser->parse();

$modelWriter = new ModelWriter($outputDirectory, $modelNamespace, $validationNamespace, $debug);
$modelWriter->write($types);

$extensionsWriter = new ExtensionsWriter($outputDirectory, $modelNamespace, $extensionsNamespace, $errorResponseName, $debug);
$extensionsWriter->write($types);