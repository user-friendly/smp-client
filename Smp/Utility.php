<?php
/**
 * @file
 * Utility class file.
 */

namespace Smp;

/**
 * Utility class.
 */
class Utility {
  
  const NEW_LINE = "\r\n";
  
  /**
   * KB constant.
   * 
   * @var integer
   */
  const KILOBYTE = 1024;
  
  static protected $printFormatBinary = '%0' . (PHP_INT_SIZE * 8) . 'b';
  
  /**
   * Generates a string representation for the given byte count.
   *
   * @param int $size
   *   A size in bytes.
   *
   * @return string
   *   A string representation of the size.
   */
  public static function formatSize(int $size) {
    $absolute_size = abs($size);
    if ($absolute_size < static::KILOBYTE) {
      return "{$size} byte" . ($absolute_size > 1 ? "s" : NULL);
    }
    foreach (['KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'] as $unit) {
      $absolute_size /= static::KILOBYTE;
      $rounded_size = \round($absolute_size, 2);
      if ($rounded_size < static::KILOBYTE) {
        break;
      }
    }
    return "{$rounded_size} {$unit}";
  }
  
  /**
   * Verbose integer binary printer.
   * 
   * @param int $binary
   * @return string
   */
  public static function formatBinary(int $binary) {
    $str = '';
    foreach (\array_chunk(\str_split(\sprintf(static::$printFormatBinary, $binary), 4), 2) as $index => $pair) {
      $offset = PHP_INT_SIZE - $index;
      $str .= "{$offset}:[{$pair[0]} {$pair[1]}] ";
    }
    return \rtrim($str);
  }
  
  // Seconds per units of time.
  public const SECONDS_HOUR = 3600;
  public const SECONDS_DAY = 86_400;
  
  // Nanoseconds contained within the other time units.
  public const NANO_SECOND = 1_000_000_000; // 1e9;
  public const NANO_MILI = 1_000_000; // 1e6;
  public const NANO_MICRO = 1_000; // 1e3;
  
  /**
   * Generate debug string for high resolution time. 
   * 
   * @param int $nano_time
   * @return string
   */
  public static function formatTimeHR(int $nano_time) {
    /*
     | Unit          | Symbol | Equivalent in Seconds     | Description                   |
     |---------------|--------|---------------------------|-------------------------------|
     | Second        | s      | 1                         | Base unit of time             |
     | Millisecond   | ms     | 0.001 (10^-3)             | Thousandth of a second        |
     | Microsecond   | us     | 0.000001 (10^-6)          | Millionth of a second         |
     | Nanosecond    | ns     | 0.000000001 (10^-9)       | Billionth of a second         |
     */
    $s = \floor($nano_time / static::NANO_SECOND);
    $ms = \floor(($nano_time % static::NANO_SECOND) / static::NANO_MILI);
    $us = \floor(($nano_time % static::NANO_MILI) / static::NANO_MICRO);
    $ns = $nano_time % static::NANO_MICRO;
    return "$s s, $ms ms, $us us, $ns ns";
  }
  
  /**
   * A \number_format() proxy.
   */
  public static function formatNumber(int|float $number) {
    return \number_format($number);
  }
  
  /**
   * Generate debug string for seconds.
   * 
   * @param int $seconds
   * @return string
   */
  public static function formatSeconds(int $seconds) {
    $days = \floor($seconds / (static::SECONDS_DAY));
    $hours = \floor(($seconds % (static::SECONDS_DAY)) / static::SECONDS_HOUR);
    $minutes = \floor(($seconds % static::SECONDS_HOUR) / 60);
    $seconds = $seconds % 60;
    $str =  "{$hours}:{$minutes}:{$seconds}";
    if ($days > 0) {
      $str = "{$days} day" . ($days > 1 ? 's' : '') . ", $str";
    }
    return $str;
  }
  
  /**
   * Gets the non-FQN class name of an object.
   * 
   * @param object|string $thing
   *   Can either be a string of a FQN class or an object.
   *    
   * @return string|FALSE
   */
  public static function get_class_name($thing) {
    if (is_object($thing)) {
      $thing = \get_class($thing);
    }
    else if (!is_string($thing)) {
      return FALSE;
    }
    if ($pos = strrpos($thing, '\\')) {
      return substr($thing, $pos + 1);
    }
    return $thing;
  }
  
  /**
   * Translate text.
   * 
   * @param string $text
   * @return string
   */
  public static function t($text) {
    return $text;
  }
  
  /**
   * Get the current timestamp.
   * 
   * A proxy to \time().
   * 
   * @return int
   */
  public static function getTime() : int {
    return \time();
  }
  
  /**
   * Get current micro timestamp.
   * 
   * A proxy to \microtime().
   * 
   * @return float
   */
  public static function getTimeMicro() : float {
    return \microtime(TRUE);
  }
  
  /**
   * Get high resolution time.
   * 
   * A proxy for \hrtime(true).
   * 
   * @return int
   */
  public static function getTimeHR() : int {
    return \hrtime(true);
  }
}
