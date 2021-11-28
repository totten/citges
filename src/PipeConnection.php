<?php

namespace Civi\Citges;

use Civi\Citges\Util\ChattyTrait;
use Civi\Citges\Util\LineReader;
use React\EventLoop\Loop;

/**
 * Setup a pipe-based connection. This starts the subprocess and provides a
 * method to send one-line requests and receive one-line responses.
 *
 * ```php
 * $p = new PipeConnection('cv ev "Civi::pipe();"')
 * $p->send('SOME COMMAND')->then(function($response){...});
 * ```
 */
class PipeConnection {

  use ChattyTrait;

  const INTERVAL = 0.1;

  private $delimiter = "\n";

  /**
   * External command used to start the pipe.
   *
   * @var string
   *   Ex: 'cv ev "Civi::pipe();"'
   */
  protected $command;

  /**
   * @var \React\ChildProcess\Process
   */
  protected $process;

  /**
   * @var \Civi\Citges\Util\LineReader
   */
  protected $lineReader;

  /**
   * @var \React\Promise\Deferred|null
   */
  protected $deferred;

  public function __construct($command) {
    $this->command = $command;
    $this->deferred = NULL;
  }

  public function start(): void {
    $this->verbose("Run: %s\n", $this->command);
    $this->process = new \React\ChildProcess\Process($this->command);
    $this->process->start();
    // $this->process->stdin->on('data', [$this, 'onReceive']);
    $this->lineReader = new LineReader($this->process->stdout, $this->delimiter);
    $this->process->stderr->on('data', [$this, 'onReceiveError']);
    $this->process->on('exit', function ($exitCode, $termSignal) {
      if ($this->deferred !== NULL) {
        $oldDeferred = $this->deferred;
        $this->deferred = NULL;
        $oldDeferred->reject('Process exited');
      }
    });
  }

  /**
   * @param string $requestLine
   *
   * @return \React\Promise\PromiseInterface
   *   Promise produces a `string` for the one-line response.
   * @throws \Exception
   */
  public function run($requestLine): \React\Promise\PromiseInterface {
    if (!$this->process->isRunning()) {
      $this->verbose("Worker disappeared. Cannot send: $requestLine\n");
      $deferred = new \React\Promise\Deferred();
      $deferred->reject("Worker disappeared. Cannot send: $requestLine\n");
      return $deferred->promise();
    }

    if (!$this->isAvailable()) {
      $this->verbose('Cannot run command. Worker is busy.');
      $deferred = new \React\Promise\Deferred();
      $deferred->reject('Cannot run command. Worker is busy.');
      return $deferred->promise();
    }

    $this->verbose("Send %s\n", $requestLine);
    $this->deferred = new \React\Promise\Deferred();
    $this->lineReader->once('readline', function ($responseLine) {
      // Handle this - unless someone else has intervened (eg stop() or on('exit')).
      if ($this->deferred) {
        $oldDeferred = $this->deferred;
        $this->deferred = NULL;
        $oldDeferred->resolve($responseLine);
      }
    });
    $this->process->stdin->write($requestLine . $this->delimiter);
    return $this->deferred->promise();
  }

  /**
   * @param float $forceTimeout
   *   If process doesn't stop with SIGTERM within $N seconds, then use
   *   SIGKILL.
   */
  public function stop($forceTimeout = 1.0): void {
    if (!$this->process->isRunning()) {
      return;
    }

    $this->process->terminate(defined('SIGTERM') ? SIGTERM : 15);
    if (!$this->process->isRunning()) {
      return;
    }

    $forceAt = microtime(TRUE) + $forceTimeout;
    $timer = Loop::addPeriodicTimer(static::INTERVAL, function() use (&$timer, $forceAt) {
      $this->verbose("Check status...\n");
      if (!$this->process->isRunning()) {
        Loop::cancelTimer($timer);
        return;
      }

      if (microtime(TRUE) > $forceAt) {
        $this->verbose("Terminate!\n");
        $this->process->terminate(defined('SIGKILL') ? SIGKILL : 9);
        Loop::cancelTimer($timer);
      }
    });

    Loop::addTimer($forceTimeout, function () {
    });
  }

  public function isAvailable(): bool {
    return $this->deferred === NULL && $this->process->isRunning();
  }

  public function onReceiveError($data) {
    if ($data === NULL || $data === '') {
      // $this->verbose("[%s @ %d]: Ignore blank %s\n", static::CLASS, posix_getpid(), $data);
      return;
    }

    $this->verbose('STDERR: ' . $data);
  }

}
