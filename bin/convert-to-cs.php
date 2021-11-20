#!/usr/bin/env php
<?php

use CWM\BroadWorksXsdConverter\CS\ModelWriter;
use CWM\BroadWorksXsdConverter\Parser;

if (is_file(__DIR__ . '/../../../autoload.php')) {
    require __DIR__ . '/../../../autoload.php';
} else {
    require __DIR__ . '/../vendor/autoload.php';
}

$defaults = [
    'models-namespace' => 'BroadWorksConnector.Ocip.Models',
    'extensions-namespace' => 'BroadWorksConnector',
    'error-response-exception-class' => 'BroadWorksConnector.Ocip.ErrorResponseException',
    'validation-namespace' => 'BroadWorksConnector.Ocip.Validation',
    'input' => null,
    'output' => null
];

$opts = array_merge($defaults, getopt('', [
    'models-namespace:',
    'extensions-namespace:',
    'error-response-exception-class:',
    'validation-namespace:',
    'debug',
    'output::',
    'input::'
]));

$debug = isset($opts['debug']);

foreach ($defaults as $key => $defaultValue) {
    if (strlen($opts[$key]) === 0) {
        echo 'Option "' . $key . '" required' . PHP_EOL;
        exit(-1);
    }
}

$parser = new Parser($opts['input'], $debug);
$types = $parser->parse();

$modelWriter = new ModelWriter($opts['output'], $opts['models-namespace'], $opts['validation-namespace'], $debug);
$modelWriter->write($types);