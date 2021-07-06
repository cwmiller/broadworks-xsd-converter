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

$defaults = [
    'model-namespace' => '\\CWM\\BroadWorksConnector\\Ocip\\Models',
    'trait-namespace' => '\\CWM\\BroadWorksConnector\\Ocip\\Traits',
    'nil-classname' => '\\CWM\\BroadWorksConnector\\Ocip\\Nil',
    'error-response-classname' => '\\CWM\\BroadWorksConnector\\Ocip\\ErrorResponseException',
    'validation-classname' => '\\CWM\\BroadWorksConnector\\Ocip\\Validation\\ValidationException',
    'input' => null,
    'output' => null
];

$opts = array_merge($defaults, getopt('', [
    'model-namespace:',
    'trait-namespace:',
    'nil-classname:',
    'error-response-classname:',
    'validation-classname:',
    'debug',
    'output::',
    'input::'
]));

foreach ($defaults as $key => $defaultValue) {
    if (strlen($opts[$key]) === 0) {
        echo 'Option "' . $key . '" required' . PHP_EOL;
        exit(-1);
    }
}

$debug = isset($opts['debug']);

$parser = new Parser($opts['input'], $debug);
$types = $parser->parse();

$modelWriter = new ModelWriter($opts['output'], $opts['model-namespace'], $opts['nil-classname'], $debug);
$modelWriter->write($types);

$traitWriter = new TraitWriter($opts['output'], $opts['model-namespace'], $opts['trait-namespace'], $opts['error-response-classname'], $opts['validation-classname'], $debug);
$traitWriter->write($types);