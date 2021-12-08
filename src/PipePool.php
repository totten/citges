<?php

namespace Civi\Citges;

use Civi\Citges\Util\FunctionUtil;
use Civi\Citges\Util\IdUtil;
use Civi\Citges\Util\PromiseUtil;
use Monolog\Logger;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

class PipePool {

  const QUEUE_INTERVAL = 0.1;

  /**
   * @var int
   * @readonly
   */
  public $id;

  /**
   * Keyed by ID
   * @var \Civi\Citges\PipeConnection[]
   */
  private $connections = [];

  /**
   * Queue of pending requests that have not been submitted yet.
   *
   * @var Todo[]
   */
  private $todos = [];

  /**
   * @var \Civi\Citges\Configuration
   */
  private $configuration;

  /**
   * @var \React\EventLoop\TimerInterface|null
   */
  private $timer;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $log;

  public function __construct(Configuration $configuration, Logger $log) {
    $this->id = IdUtil::next(__CLASS__);
    $this->configuration = $configuration;
    $this->log = $log ? $log->withName('PipePool_' . $this->id) : new Logger('PipePool_' . $this->id);
  }

  /**
   * @return \React\Promise\PromiseInterface
   *   A promise which returns the online pool.
   */
  public function start(): PromiseInterface {
    $this->log->info("Start");
    $this->timer = Loop::addPeriodicTimer(static::QUEUE_INTERVAL, FunctionUtil::singular([$this, 'checkQueue']));
    return resolve($this);
  }

  /**
   * @param float $timeout
   *   If a process doesn't stop within $timeout, escalate to more aggressive forms of stopping.
   * @return \React\Promise\PromiseInterface
   *   A promise which returns when all subprocesses have stopped.
   */
  public function stop(float $timeout = 1.5): PromiseInterface {
    $this->log->info("Stopping");
    Loop::cancelTimer($this->timer);
    $this->timer = NULL;
    $all = [];
    foreach ($this->connections as $connection) {
      if (!$connection->isMoribund()) {
        $all[] = $connection->stop($timeout);
      }
      // TODO: Can we wait on the moribund ones that are already stopping?
    }
    return all($all);
  }

  /**
   * Dispatch a single request.
   *
   * @param string $context
   * @param string $requestLine
   * @return \React\Promise\PromiseInterface
   *   A promise for the response data.
   */
  public function dispatch(string $context, string $requestLine): \React\Promise\PromiseInterface {
    $this->log->debug("Enqueue ({context}): {requestLine}", ['context' => $context, 'requestLine' => $requestLine]);
    $todo = new Todo($context, $requestLine);
    $this->todos[] = $todo;
    return $todo->deferred->promise();
  }

  /**
   * Periodic polling function. Check for new TODOs. Start/stop connections, as needed.
   *
   * @throws \Exception
   */
  public function checkQueue(): void {
    while (TRUE) {
      if (empty($this->todos)) {
        // Nothing to do... try again later...
        return;
      }

      /** @var \Civi\Citges\Todo $todo */
      $todo = $this->todos[0];
      $startTodo = function() use ($todo) {
        if ($todo !== $this->todos[0]) {
          throw new \RuntimeException('Failed to dequeue expected task.');
        }
        array_shift($this->todos);
        $this->log->debug("Executing", ['requestLine' => $todo->request]);
      };

      // Re-use existing/idle connection?
      if ($connection = $this->findIdleConnection($todo->context)) {
        $startTodo();
        PromiseUtil::chain($connection->run($todo->request), $todo->deferred);
        continue;
      }

      // We want to make a new connection... Is there room?
      if (count($this->connections) >= $this->configuration->maxWorkers) {
        $this->cleanupConnections($this->configuration->gcWorkers);
      }

      if (count($this->connections) >= $this->configuration->maxWorkers) {
        // Not ready yet. Keep $todo in the queue and wait for later.
        return;
      }
      else {
        // OK, we can use this $todo... on a new connection.
        $startTodo();
        $this->addConnection($todo->context)->then(function ($connection) use ($todo) {
          PromiseUtil::chain($connection->run($todo->request), $todo->deferred);
        });
      }
    }
  }

  /**
   * Cleanup unnecessary references. These may include dead/disappeared workers, idle workers,
   * or workers that have been running for a long time.
   *
   * @param int $goalCount
   *   The number of workers we would like to remove.
   * @return int
   *   The number actually removed.
   */
  public function cleanupConnections(int $goalCount): int {
    $this->log->debug("cleanupConnections");
    // Score all workers - and decide which ones we can remove.

    // Priority: remove crashed processes; then idle/exhausted processes; then idle/non-exhausted processes.
    $getScore = function(PipeConnection $c) {
      $running = $c->isRunning();
      $idle = $c->isIdle();
      $exhausted = $c->isExhausted($this->configuration);

      // Positive scores are allowed to be removed. Negatives must be kept.
      if (!$running) {
        return 20;
      }
      if ($running && $idle && $exhausted) {
        return 10;
      }
      if ($running && $idle && !$exhausted) {
        return 5;
      }
      if ($running && !$idle) {
        return -10;
      }
      throw new \RuntimeException("Failed to score worker");
    };

    $sorted = new \SplPriorityQueue();
    foreach ($this->connections as $connection) {
      /** @var \Civi\Citges\PipeConnection $connection */
      $score = $getScore($connection);
      if ($score > 0) {
        $sorted->insert($connection, $score);
      }
    }

    $removedCount = 0;
    foreach ($sorted as $connection) {
      /** @var \Civi\Citges\PipeConnection $connection */
      if ($removedCount >= $goalCount) {
        break;
      }
      $this->removeConnection($connection->id);
      $removedCount++;
    }
    return $removedCount;
  }

  private function findIdleConnection(string $context): ?PipeConnection {
    foreach ($this->connections as $connection) {
      if ($connection->context === $context && $connection->isIdle() && !$connection->isExhausted($this->configuration)) {
        return $connection;
      }
    }
    return NULL;
  }

  /**
   * @param string $context
   * @return \React\Promise\PromiseInterface
   *   A promise for the new/started instance of PipeConnection.
   */
  private function addConnection(string $context): PromiseInterface {
    $connection = new PipeConnection($this->configuration, $context, $this->log);
    $this->connections[$connection->id] = $connection;
    return $connection->start()->then(function($welcome) use ($connection) {
      $this->log->debug('Started connection', ['welcome' => $welcome]);
      return $connection;
    });
  }

  /**
   * @param int $connectionId
   * @return \React\Promise\PromiseInterface
   *   A promise for the stopped instance of PipeConnection.
   */
  private function removeConnection(int $connectionId): PromiseInterface {
    $connection = $this->connections[$connectionId];
    unset($this->connections[$connectionId]);
    return $connection->stop()->then(function() use ($connection) {
      $this->log->debug('Stopped connection');
      return $connection;
    });
  }

}
