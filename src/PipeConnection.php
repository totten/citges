<?php

namespace Civi\Citges;

use Civi\Citges\Util\ChattyTrait;
use Civi\Citges\Util\IdUtil;
use Civi\Citges\Util\LifetimeStatsTrait;
use Civi\Citges\Util\LineReader;
use Civi\Citges\Util\ProcessUtil;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Setup a pipe-based connection. This starts the subprocess and provides a
 * method to send one-line requests and receive one-line responses.
 *
 * ```php
 * $p = new PipeConnection(new Configuration('cv ev "Civi::pipe();"');
 * $p->send('SOME COMMAND')->then(function($response){...});
 * ```
 */
class PipeConnection {

  use ChattyTrait;
  use LifetimeStatsTrait;

  /**
   * @var int
   * @readonly
   */
  public $id;

  /**
   * @var string
   * @readonly
   */
  public $context;

  /**
   * @var Configuration
   */
  public $configuration;

  private $delimiter = "\n";

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

  public function __construct(Configuration $configuration, ?string $context = NULL) {
    $this->id = IdUtil::next(__CLASS__);
    $this->context = $context;
    $this->configuration = $configuration;
    $this->deferred = NULL;
  }

  /**
   * Launch the worker process.
   *
   * @return \React\Promise\PromiseInterface
   *   The promise returns when the pipe starts.
   *   It will report the welcome line.
   */
  public function start(): PromiseInterface {
    $this->verbose("Starting: %s\n", $this->configuration->pipeCommand);
    $this->startTime = microtime(TRUE);

    // We will receive a 1-line welcome which signals that startup has finished.
    $this->reserveDeferred();

    $this->process = new \React\ChildProcess\Process($this->configuration->pipeCommand);
    $this->process->start();
    // $this->process->stdin->on('data', [$this, 'onReceive']);
    $this->lineReader = new LineReader($this->process->stdout, $this->delimiter);
    $this->lineReader->on('readline', [$this, 'onReadLine']);
    $this->process->stderr->on('data', [$this, 'onReceiveError']);
    $this->process->on('exit', function ($exitCode, $termSignal) {
      $this->endTime = microtime(TRUE);
      if ($this->deferred !== NULL) {
        $oldDeferred = $this->deferred;
        $this->deferred = NULL;
        $oldDeferred->reject('Process exited');
      }
    });

    $this->verbose("Forked\n");
    return $this->deferred->promise();
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
      $this->verbose("Worker disappeared. Cannot send: $requestLine");
      $deferred->reject("Worker disappeared. Cannot send: $requestLine");
      return $deferred->promise();
    }

    $this->verbose("Send %s\n", $requestLine);
    $this->process->stdin->write($requestLine . $this->delimiter);
    return $deferred->promise();
  }

  /**
   * Shutdown the worker.
   *
   * If there is a pending request, it may be aborted and may report failure.
   *
   * @param float $timeout
   *   stop() will initially try a gentle shutdown by closing STDIN.
   *   If it doesn't end within $timeout, it will escalate the means of stopping.
   * @return \React\Promise\PromiseInterface
   *   A promise that returns once shutdown is complete.
   * @throws RuntimeException
   *   If you attempt to stop multiple times, subsequent calls will throw an exception.
   */
  public function stop(float $timeout = 1.5): PromiseInterface {
    if ($this->moribund) {
      throw new \RuntimeException("Process is already stopping or stopped.");
    }
    $this->setMoribund(TRUE);
    $this->verbose("Stopping\n");
    return ProcessUtil::terminateWithEscalation($this->process, $timeout)
      ->then(function($data) {
        $this->verbose("Stopped\n");
        return $data;
      });
  }

  /**
   * Is the agent currently idle - or busy with a request?
   *
   * @return bool
   */
  public function isIdle(): bool {
    return $this->deferred === NULL;
  }

  /**
   * Is the agent currently online?
   *
   * @return bool
   */
  public function isRunning(): bool {
    return $this->process->isRunning();
  }

  /**
   * @param string $responseLine
   * @internal
   */
  public function onReadLine(string $responseLine): void {
    if ($this->deferred) {
      $this->releaseDeferred()->resolve($responseLine);
    }
    else {
      $this->verbose("Received unexpected response line: %s\n", $responseLine);
    }
  }

  /**
   * @param $data
   * @internal
   */
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

  protected function verbose($msg, ...$args): void {
    $msg = '[%.1f #%d %s #%d-%s] ' . $msg;
    array_unshift($args,
      microtime(TRUE),
      posix_getpid(),
      static::CLASS,
      $this->id,
      $this->process ? $this->process->getPid() : '?');
    call_user_func('printf', $msg, ...$args);
  }

}
