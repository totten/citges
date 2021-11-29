<?php

namespace Civi\Citges;

use Civi\Citges\Util\ChattyTrait;
use Civi\Citges\Util\LifetimeStatsTrait;
use Civi\Citges\Util\LineReader;
use React\EventLoop\Loop;
use React\Promise\Deferred;

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
  use LifetimeStatsTrait;

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
   * If there is a pending request, this is the deferred use to report on the request.
   * If there is no pending request, then null.
   *
   * @var \React\Promise\Deferred|null
   */
  protected $deferred;

  public function __construct($command) {
    $this->command = $command;
    $this->deferred = NULL;
  }

  /**
   * Launch the worker process.
   */
  public function start(): void {
    $this->verbose("Run: %s\n", $this->command);
    $this->startTime = microtime(TRUE);
    $this->process = new \React\ChildProcess\Process($this->command);
    $this->process->start();
    // $this->process->stdin->on('data', [$this, 'onReceive']);
    $this->lineReader = new LineReader($this->process->stdout, $this->delimiter);
    $this->process->stderr->on('data', [$this, 'onReceiveError']);
    $this->process->on('exit', function ($exitCode, $termSignal) {
      $this->endTime = microtime(TRUE);
      if ($this->deferred !== NULL) {
        $oldDeferred = $this->deferred;
        $this->deferred = NULL;
        $oldDeferred->reject('Process exited');
      }
    });
  }

  /**
   * Send a request to the worker, and receive an async response.
   *
   * Worker protocol only allows one active request. If you send a second
   * request while the first remains pending, it will be rejected.
   *
   * @param string $requestLine
   *
   * @return \React\Promise\PromiseInterface
   *   Promise produces a `string` for the one-line response.
   * @throws \Exception
   */
  public function run($requestLine): \React\Promise\PromiseInterface {
    $this->requestCount++;
    $deferred = $this->reserveDeferred();

    if (!$this->process->isRunning()) {
      $this->releaseDeferred();
      $deferred->reject("Worker disappeared. Cannot send: $requestLine");
      return $deferred->promise();
    }

    $this->verbose("Send %s\n", $requestLine);
    $this->lineReader->once('readline', function ($responseLine) {
      // Handle this response - unless someone else has intervened (eg stop() or on('exit')).
      if ($this->deferred) {
        $this->releaseDeferred()->resolve($responseLine);
      }
    });
    $this->process->stdin->write($requestLine . $this->delimiter);
    return $deferred->promise();
  }

  /**
   * Shutdown the worker.
   *
   * If there is a pending request, it will likely be aborted and report failure.
   *
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
    $timer = Loop::addPeriodicTimer(static::INTERVAL, function () use (&$timer, $forceAt) {
      // $this->verbose("Check status...\n");
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

  /**
   * Is this agent able to accept requests?
   *
   * @return bool
   */
  public function isAvailable(): bool {
    return $this->deferred === NULL && $this->process->isRunning();
  }

  public function onReceiveError($data): void {
    if ($data === NULL || $data === '') {
      // $this->verbose("[%s @ %d]: Ignore blank %s\n", static::CLASS, posix_getpid(), $data);
      return;
    }

    $this->verbose('STDERR: ' . $data);
  }

  /**
   * Reserve this worker. Set the stub for the pending request
   * ($this->deferred).
   *
   * @return \React\Promise\Deferred
   */
  private function reserveDeferred(): Deferred {
    if ($this->deferred !== NULL) {
      throw new \RuntimeException("Cannot send request. Worker is busy.");
    }

    $this->deferred = new \React\Promise\Deferred();
    return $this->deferred;
  }

  /**
   * Release this worker. Unset the pending request ($this->deferred).
   *
   * @return \React\Promise\Deferred
   */
  private function releaseDeferred(): Deferred {
    $oldDeferred = $this->deferred;
    $this->deferred = NULL;
    return $oldDeferred;
  }

}
