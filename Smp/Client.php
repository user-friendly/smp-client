<?php
/**
 * @file
 * A simple client.
 * 
 * @deprecated Not really, just refactor it and move it somewhere else.
 * It will probably be used for testing.
 */

namespace Smp;

use Smp\Logger\LoggerTrait;
use Smp\Network\Connection;

/**
 * Client class.
 * 
 * Probably going to use it for testing only.
 */
class Client {
  use LoggerTrait;
  
  const VERSION = '1.0.0-alpha3';
  
  const EXIT_SUCCESS = 0;
  const EXIT_FAILURE = 1;
  
  /**
   * @var \Smp\Process;
   */
  protected $process;
  
  // The socket path should always be absolute.
  protected $socketPath;
  protected $socketUrl;
  
  /**
   * Connection.
   * 
   * @var \Smp\Network\Connection
   */
  protected $connection;
  
  /**
   * Default client connection timeout.
   * 
   * This is the stream I/O max wait time, in blocking mode only.
   *
   * @var integer
   */
  protected $connectionTimeoutDefault = 60;
  
  /**
   * Maximum time to wait for the server to accept the connection.
   * 
   * @var integer
   */
  protected $connectWaitTime = 60;
  
  // Max connect retries.
  protected $retries = 8;
  
  protected $stats = [
    "bytes_read" => 0,
    "bytes_written" => 0,
  ];
  
  protected $outputFd = STDOUT;
  protected $inputFd = STDIN;
  
  public function __construct() {
    $this->logger = static::getLogger();
    
    $this->process = new Process();
    
    // The socket path should always be absolute.
    //$this->socketPath = \getcwd() . "/php-daemon.sock";
    //$this->socketUrl = "unix://{$this->socketPath}";
    $this->socketUrl = 'tcp://0.0.0.0:9999';
    
    $this->process->addSignalHandler([$this, "shutdown"]);
    
    // Setup input stream.
    \stream_set_blocking($this->inputFd, false);
  }
  
  public function run() : int {
    $this->logger->info("Simple MUD in PHP Client.");
    $this->logger->info("Version: " . $this::VERSION);
    
    $this->connect();
    
    $cmd_disconnect = ["quit", "exit", "disconnect"];
    
    while ($this->connection->isValid()) {
      $this->connection->receive();
      $this->connection->send();
      
      if ($output = $this->connection->consume()) {
        $this->print("$output\n");
        $this->printPrompt();
      }
      
      if ($input = $this->readInput()) {
        foreach (\preg_split("/\n|;/", $input) as $line) {
          if ($line[0] === '\\') {
            $line = \trim(\substr($line, 1));
            if (\in_array($line, $cmd_disconnect)) {
              $this->logger->warning("Client-side disconnect.");
              break 2;
            }
            else {
              $this->logger->error("Client command `$line` not found.");
            }
          }
          else if ($line) {
            $this->connection->append($line);
          }
        }
        $this->printPrompt();
      }
      
      $this->process->yield();
    }
    
    $this->shutdown();
    
    return $this::EXIT_SUCCESS;
  }
  
  protected function printPrompt() {
    $this->print("client> ");
  }
  
  protected function print($output) : int|false {
    $bytes = \fwrite($this->outputFd, $output);
    // \fflush(STDOUT);
    return $bytes;
  }
  
  protected function readInput() : string|false {
    return \fread(STDIN, 1024);
  }
  
  protected function connect() : void {
    $this->logger->debug("connect to {$this->socketUrl}");
    $socket = NULL;
    for ($retries = 0; $retries < $this->retries; $retries++) {
      $errno = 0;
      $errstr = 'NULL';
      $socket = @\stream_socket_client($this->socketUrl, $errno, $errstr, $this->connectWaitTime, STREAM_CLIENT_CONNECT);
      if ($socket) {
        break;
      }
      else {
        $this->logger->error("failed to connect to $this->socketUrl, error: $errstr ($errno).");
        $this->logger->debug("retry connection ($retries)...");
        // FIXME A bunch of magic here.
        $this->process->sleep(\mt_rand(50, 500) / 1000);
      }
    }
    if (!$socket) {
      throw new \RuntimeException("failed to connect to {$this->socketUrl}. Give up.");
    }
    
    $this->connection = new Connection($socket, FALSE, 1 /* $this->connectionTimeoutDefault */);
    $this->logger->debug("connected after {$retries} retries.");
  }
  
  public function shutdown() {
    if ($this->connection->isValid()) {
      $this->logger->debug("closing connection");
      $this->connection->close();
    }
    // TODO Do the stats.
    // $this->logger->debug("shutdown. Total bytes sent: {$this->stats}");
  }
}
