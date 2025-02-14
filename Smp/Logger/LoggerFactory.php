<?php
/**
 * @file
 * LoggerFactory class file.
 */

namespace Smp\Logger;

use \Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

/**
 * LoggerFactory class.
 */
class LoggerFactory {
  /**
   * @var \Monolog\Logger[]
   */
  protected static $loggers = [];
  
  /**
   * @var \Monolog\Handler\HandlerInterface[]
   */
  protected static $loggersHandlersDefault = [];
  
  /**
   * Get a logger with a specific channel.
   *
   * @param string $channel
   * @return \Monolog\Logger
   */
  public static function getLogger(string $channel) : \Monolog\Logger {
    return static::$loggers[$channel]
      ?? (static::$loggers[$channel] = new \Monolog\Logger($channel, static::$loggersHandlersDefault));
  }
  
  /**
   * Set default logger handlers.
   *
   * @param \Monolog\Handler\HandlerInterface $handlers[]
   */
  public static function setLoggerDefaultHandlers(array $handlers) {
    static::$loggersHandlersDefault = $handlers;
  }
  
  public static function init() {
    if (empty(static::$loggersHandlersDefault)) {
      // Get log level from configurations (include cmd line args) and override
      // this value.
      $log_level = DEBUG ? Level::Debug : Level::Info;
      $log_file = DEBUG ? STDOUT : STDERR;
      
      $stream_handler = new StreamHandler($log_file, $log_level);
      $format = DEBUG ? "U" : NULL ;
      $formatter = new LineFormatter(NULL, $format, TRUE, TRUE);
      $stream_handler->setFormatter($formatter);
      
      LoggerFactory::setLoggerDefaultHandlers([$stream_handler]);
    }
  }
}
