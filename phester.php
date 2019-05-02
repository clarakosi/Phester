#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Bridge\Monolog\Logger;
use API\TestCommand;

$logger = new Logger( 'Phester' );
$application = new Application();

$application->add( new TestCommand( $logger ) );
$application->run();
