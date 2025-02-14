<?php
/**
 * @file
 * Main executable.
 */

// FIXME Remove, at first beta version.
define('DEBUG', TRUE);

require __DIR__ . '/vendor/autoload.php';

Smp\Logger\LoggerFactory::init();

// Command line arguments/options handling example.
//require __DIR__ . '/Smp/Cli.php';
//exit(1);

$return = (new \Smp\Client())->run();
if ($return !== 0) {
  exit($return);
}
