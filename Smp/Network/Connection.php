<?php
/**
 * @file
 * Connection class file.
 */

// FIXME Inconsistent error handling - are you going to throw exceptions or return
//       FALSE?!

// FIXME Can probably be moved into Server. Too generic. Needs to be simplified.

// TODO Document the magic getters and setters.

namespace Smp\Network;

use Monolog\Logger;
use Smp\Logger\LoggerTrait;

/**
 * A stream connection class.
 */
class Connection {
  use LoggerTrait;

  const KILO_BYTE = 1024;
  
  /**
   * Stream resource.
   * 
   * @var resource
   */
  protected $stream;
  
  protected $blockMode;
  
  protected $timeout;
  
  protected $remoteName;
  
  protected $remoteIp;
  
  protected $remotePort;
  
  /**
   * Stream read size.
   * 
   * @var int
   */
  protected $readSize = NULL;
  
  /**
   * Stream write size.
   * 
   * @var int
   */
  protected $writeSize = NULL;
  
  /**
   * Unbound read buffer.
   * 
   * @var string
   */
  protected $readBuffer = '';
  
  /**
   * Unbound write buffer.
   * 
   * @var string
   */
  protected $writeBuffer = '';
  
  /**
   * A list of readonly protected variables.
   * 
   * @TODO This is not a good idea. Refactor.
   */
  protected static $getters = [
    "blockMode", "timeout", "remoteName", "readSize", "writeSize",
  ];
  
  /**
   * Connection constructor. 
   * 
   * @param resource $stream
   *   A stream resource, ready for I/O.
   */
  public function __construct($stream, $blockMode = TRUE, $timeout = 60, $readSize = NULL, $writeSize = NULL) {
    $this->logger = static::getLogger();
    
    if (!\is_resource($stream)) {
      throw new \RuntimeException("Connection needs a valid stream resource.");
    }
    $this->stream = $stream;
    
    if ($readSize === NULL) {
      $readSize = static::KILO_BYTE;
    }
    if ($writeSize === NULL) {
      $writeSize = static::KILO_BYTE;
    }
    
    // Setup stream properties.
    $this->setBlockMode($blockMode);
    $this->setTimeout($timeout);
    $this->setReadSize($readSize);
    $this->setWriteSize($writeSize);
    
    // TODO Read more about the TCP Nagle algorithm. It is enabled by default.
    // \stream_context_set_option($stream, 'socket', 'tcp_nodelay', TRUE);

    $this->remoteName = @\stream_socket_get_name($this->stream, TRUE);
    
    $this->remoteIp = \substr($this->remoteName, 0, \strrpos($this->remoteName, ':'));
    $this->remotePort = \abs(\intval(\substr($this->remoteName, \strrpos($this->remoteName, ':') + 1)));
    
    if (DEBUG) {
      $this->logger->debug("Remote socket: " . $this->remoteName);
    }
  }
  
  /**
   * Close the stream connection.
   * 
   * Optionally, flush the write buffer.
   * 
   * @param bool $flush
   *   Attempt to send any remaining data. Depending on what blocking mode the
   *   stream is in, flushing is potentially a blocking operation.
   * 
   * @return bool
   *   Returns TRUE if the stream was closed successfully. FALSE otherwise.
   */
  public function close(bool $flush = FALSE) : bool {
    if ($flush) {
      // Attempt to flush the write buffer.
      // FIXME Potential infinite loop.
      while ($this->writeBuffer) {
        $this->send();
      }
    }
    return \fclose($this->stream);
  }
  
  // TODO Test method?
  public function shutdown($mode = \STREAM_SHUT_RD) : bool {
    return \stream_socket_shutdown($this->stream, $mode);
  }
  
  /**
   * Receive data.
   */
  public function receive() : self {
    if (!$this->isValid()) {
      return $this;
    }
    
    $read_buff = $this->readSome($this->readSize);
    
    if (\strlen($read_buff)) {
      $this->readBuffer .= $read_buff;
      
      if (DEBUG) {
        $sz = \strlen($read_buff);
        $hex = \bin2hex($read_buff);
//         $this->logger->debug("{$this->remoteName} received {$sz} bytes: {$hex}");
//         $this->logger->debug("{$this->remoteName}: \n{$read_buff}");
      }
    }
    return $this;
  }
  
  /**
   * Sends data to the receiver.
   */
  public function send() : self {
    if (!$this->isValid()) {
      return $this;
    }
    
    if (\strlen($this->writeBuffer) > 0) {
      // Peek the packet to be sent.
      $binary = \substr($this->writeBuffer, 0, $this->writeSize);
      // TODO Catch exception?
      $this->sendSome($binary);
      // Consume bytes from the write buffer. Should be an expensive memory
      // allocation operation.
      $this->writeBuffer = \substr($this->writeBuffer, $this->writeSize);
    }
    return $this;
  }
  
  /**
   * Check whether the connection is valid.
   * 
   * A valid connection is one that has a stream resources and is connected to
   * a client.
   * 
   * @return boolean
   *   True if the connection is valid, false otherwise.
   */
  public function isValid() {
    return \is_resource($this->stream) && !\feof($this->stream);
  }

  public function __get(string $name) {
    if (\in_array($name, static::$getters)) {
      return $this->$name;
    }
    if (DEBUG && \strpos($name, '_debug', 0) === 0) {
      return $this->{$name};
    }
    return NULL;
  }
  
  public function __set(string $name, $value) : void {
    $method_name = "set" . \ucfirst($name);
    if (\method_exists($this, $method_name)) {
      $this->$method_name($value);
    }
    if (DEBUG && \strpos($name, '_debug', 0) === 0) {
      $this->{$name} = $value;
    }
  }
  
  public function __isset(string $name) {
    if (\in_array($name, static::$getters)) {
      return isset($this->$name);
    }
    if (DEBUG && \strpos($name, '_debug', 0) === 0) {
      return isset($this->{$name});
    }
    return NULL;
  }
  
  /**
   * Consumes up to $size bytes from the read buffer.
   *
   * @param int $size
   *   Optional. Byte read limit.
   *
   * @return string|boolean
   *   Returns a binary string or FALSE if buffer was less than $size.
   *
   */
  public function consume(int $size = 0) {
    $size = $size ? $size : $this->readSize;
    if (\strlen($this->readBuffer)) {
      $binary = \substr($this->readBuffer, 0, $size);
      $this->readBuffer = \substr($this->readBuffer, \strlen($binary));
      return $binary;
    }
    return FALSE;
  }
  
  /**
   * Consumes exactly $size bytes from the read buffer.
   *
   * @param int $size
   *   Number of bytes to read (exactly).
   *
   * @return string|boolean
   *   Returns a binary string of size $size or FALSE if buffer was less than
   *   $size.
   *
   */
  public function consumeExact(int $size = 0) {
    $size = $size ? $size : $this->readSize;
    if (\strlen($this->readBuffer) >= $size) {
      $binary = \substr($this->readBuffer, 0, $size);
      $this->readBuffer = \substr($this->readBuffer, $size);
      return $binary;
    }
    return FALSE;
  }
  
  /**
   * Peek exactly $size bytes from the read buffer.
   * 
   * Unlike ::consume() & ::consumeExact(), the read buffer is not altered.
   * 
   * @param int $size
   * @return string|boolean
   *   Returns the binary string or FALSE if buffer was less than $size.
   */
  public function peek(int $size) {
    if (\strlen($this->readBuffer) >= $size) {
      return \substr($this->readBuffer, 0, $size);
    }
    return FALSE;
  }
  
  /**
   * Append to the write buffer.
   * 
   * @param string $binary
   */
  public function append(string $binary) : self {
    $this->writeBuffer .= $binary;
    return $this;
  }
  
  /**
   * Get the current receive buffer size.
   * 
   * @return number
   */
  public function getRecvSize() {
    return \strlen($this->readBuffer);
  }
  
  /**
   * Get the current send buffer size.
   * 
   * @return number
   */
  public function getSendSize() {
    return \strlen($this->writeBuffer);
  }
  
  /**
   * Read some binary data from stream.
   *
   * Do not use this method to send data unless you know what you're doing.
   *
   * @see \fread()
   *
   * @param int $size
   * @throws \RuntimeException
   * @return string
   *   Returned binary data.
   */
  protected function readSome(int $size = 1500) : string {
    $binary = \fread($this->stream, $size);
    if ($binary === FALSE) {
      $error = \error_get_last();
      \error_clear_last();
      \fclose($this->stream);
      throw new \RuntimeException($error['message'] ? $error['message'] : 'Unknown stream read error.');
    }
    // TODO In DEBUG mode, save byte count to stats
    return $binary;
  }
  
  /**
   * Write some binary data to stream.
   *
   * Do not use this method to send data unless you know what you're doing.
   *
   * @see \fwrite()
   *
   * @param string $binary
   *   Bytes to be written, as a string or as an array of bytes.
   * @throws \RuntimeException
   *   In case there's a mismatch between bytes to be written and bytes written.
   * @return int
   *   Number of bytes written.
   */
  protected function sendSome(string $binary) : int {
    $bytes_written = \fwrite($this->stream, $binary);
    if (\strlen($binary) !== $bytes_written) {
      $error = \error_get_last();
      \error_clear_last();
      \fclose($this->stream);
      throw new \RuntimeException($error['message'] ? $error['message'] : 'Unknown stream write error.');
    }
    // TODO In DEBUG mode, save byte count to stats
    return $bytes_written;
  }

  protected function setBlockMode(bool $blocking) : self {
    $this->blockMode = $blocking;
    if (!\stream_set_blocking($this->stream, $this->blockMode)) {
      $this->logger->error("Failed to set stream blocking mode to: {$this->blockMode}");
    }
    return $this;
  }
  
  protected function setTimeout(int $timeout) : self {
    $this->timeout = $timeout;
    if (!\stream_set_timeout($this->stream, $this->timeout)) {
      $this->logger->error("Failed to set stream timeout to: {$this->timeout}");
    }
    return $this;
  }
  
  protected function setReadSize(int $size) : self {
    $this->readSize = $size;
    return $this;
  }
  
  protected function setWriteSize(int $size) : self {
    $this->writeSize = $size;
    return $this;
  }
  
  /**
   * Get the remote IP.
   * 
   * @return string
   */
  public function getIp() : string {
    return $this->remoteIp;
  }
  
  /**
   * Get the remote port.
   * 
   * @return int
   */
  public function getPort() : int {
    return $this->remotePort;
  }
  
  /**
   * Get the remote address.
   * 
   * @return string
   */
  public function getAddress() : string {
    return $this->remoteName;
  }
  
  public static function debugStreamInfo($stream, Logger $logger) {
    $logger->debug("Socket open: " . \stream_socket_get_name($stream, FALSE));
    
    if (\stream_supports_lock($stream)) {
      $logger->debug("Socket supports locks.");
    }
    else {
      $logger->debug("Socket does not support locks.");
    }
    
    if (\stream_is_local($stream)) {
      $logger->debug("Socket is local.");
    }
    else {
      $logger->debug("Socket is not local.");
    }
    
    if (\stream_isatty($stream)) {
      $logger->debug("Socket is tty.");
    }
    else {
      $logger->debug("Socket is not tty.");
    }
  }
}
