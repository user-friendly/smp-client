<?php
/**
 * @file
 * Main executable.
 */

// FIXME Remove, at first beta version.
define('DEBUG', TRUE);

require __DIR__ . '/vendor/autoload.php';

Smp\Logger\LoggerFactory::init();

$return = (new \Smp\Client())->run();
if ($return !== 0) {
  exit($return);
}
