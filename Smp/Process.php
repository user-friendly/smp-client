<?php
/**
 * @file
 * Process class file.
 */

namespace Smp;

use Smp\Logger\LoggerTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Manager class for the current process.
 */
class Process {
  use LoggerTrait;
  
  /**
   * Process id.
   *
   * @var int
   */
  protected $pid;
  
  /**
   * Parent process id.
   *
   * @var int
   */
  protected $ppid;
  
  /**
   * @var boolean
   */
  protected $isChild = FALSE;
  
  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcher;
   */
  protected $signalDispatcher;
  
  protected $signalTermReceived = FALSE;
  
  // TODO This is silly.
  static protected $getters = [
    'pid', 'ppid', 'isChild',
  ];
  
  public function __get($name) {
    if (in_array($name, static::$getters)) {
      return $this->$name;
    }
    return NULL;
  }
  
  public function __isset($name) {
    if (in_array($name, static::$getters)) {
      return isset($this->$name);
    }
    return NULL;
  }
  
  public function __toString() : string {
    return "{process#{$this->pid}}";
  }
  
  /**
   * Termination signals.
   *
   * @var array
   */
  static protected $termination_signals = [
    SIGTERM,
    SIGINT,
    SIGHUP,
    SIGQUIT,
  ];
  
  const NANO_SECOND = 1000000000;
  static public function sleep(float $time) : void {
    // TODO WTF? Comment your code.
    \time_nanosleep(
      (int) $time,
      (int) (($time - (int) $time) * self::NANO_SECOND)
      );
  }
  
  /**
   * Poor man's yield.
   */
  static public function yield() {
    // FIXME Magic number.
    // FIXME Either make this a constant or calculate it once per runtime, and directly use \time_nanosleep().
    static::sleep(0.025);
  }
  
  /**
   * Main class ctor.
   *
   * @param int $argc
   * @param array $argv
   */
  public function __construct() {
    $this->logger = static::getLogger();
    $this->signalDispatcher = new EventDispatcher();
    
    $this->setupSignalHandler();
    $this->updateProcessIds();
  }
  
  /**
   * Create a new child process.
   * 
   * @see \pcntl_fork()
   * @throws \RuntimeException
   * @return int
   *   Either 0 for the child process or the child's pid for the parent process.
   */
  public function fork() : int {
    $child_pid = \pcntl_fork();
    if ($child_pid == -1) {
      $errno = \pcntl_get_last_error();
      throw new \RuntimeException(
          "Failed to create child process. \pcntl_fork() error: $errno",
          $errno
        );
    }
    else if ($child_pid) {
      $this->logger->debug("Child process created: $child_pid");
    }
    else {
      // Child process setup.
      $this->updateProcessIds();
      // Only this method should set child status, other than the default.
      $this->isChild = TRUE;
    }
    return $child_pid;
  }
  
  // FIXME Misleading name.
  /**
   * Safe fork.
   * 
   * @throws \RuntimeException
   * @return int|NULL
   *    Eqivalent to ::fork(), except NULL is returned in case this is a child
   *    process.
   */
  public function forkSafe() {
    if ($this->isChild) {
      return NULL;
    }
    return $this->fork();
  }
  
  public function execute($sleep = 60) : int {
    while (true) {
      static::yield();
    }
    
    $this->logger->debug("Process {$this->pid}(parent {$this->ppid}) is done.");
    
    return Game::EXIT_SUCCESS;
  }
  
  public function addSignalHandler($handler) {
    $this->signalDispatcher->addListener("parallel.process.signal", $handler);
  }
  
  public function removeSignalHandler($handler) {
    $this->signalDispatcher->removeListener("parallel.process.signal", $handler);
  }
  
  protected function updateProcessIds() {
    $this->pid = \posix_getpid();
    $this->ppid = \posix_getppid();    
    $this->logger->debug("Update process ids to {$this->pid}({$this->ppid})");
  }
  
  protected function setupSignalHandler()
  {
    // See https://wiki.php.net/rfc/async_signals
    if (!\pcntl_async_signals()) {
      \pcntl_async_signals(true);
    }
    
    $generic_handler = [$this, 'signalHandler'];
    $signals = [
      // Cannot be handled/ignored.
      // SIGKILL,
      SIGUSR1,
    ];
    $signals = \array_merge(static::$termination_signals, $signals);
    foreach ($signals as $signo) {
      \pcntl_signal($signo, $generic_handler);
    }
  }
  
  // Has to be public? What a drag.
  public function signalHandler(int $signo, $siginfo) : void {
    $this->signalDispatcher->dispatch(new GenericEvent($signo, [$siginfo]), "parallel.process.signal");
    
    if (in_array($signo, static::$termination_signals)) {
      $this->logger->warning("Forced shutdown of {$this->pid}({$this->ppid}).");
      // Termination requested.
      // TODO Try to gracefully shutdown the deamon and its children.
      // TODO Do not wait on child processes. Notify them instead of the manager
      //      process termination.
      //      Edit: signals seem to be passed to the child processes
      // FIXME This does not look good. What are the standard shutdown Unix procedures?
      if ($this->signalTermReceived) {
        exit(Game::EXIT_SUCCESS);
      }
      else {
        $this->signalTermReceived = TRUE;
      }
    }
    
    // All other signals handled here.
    switch ($signo) {
      case SIGUSR1:
        $this->logger->debug("Received SIGUSR1.");
        break;
      case SIGALRM:
        $this->logger->debug("Received SIGALARM.");
        break;
      default:
        // Unknown signal received.
        break;
    }
  }
}
