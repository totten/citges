<?php

namespace Civi\Citges;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

/**
 * Monitor CiviCRM queues for new tasks - and execute them.
 *
 * The basic process is a round-robin scan (visiting each queue, 1-by-1);
 * it runs in a loop with these steps:
 *
 * 1. fillSteps(): Get a list of live queues. Plan a step for visiting each.
 * 2. runQueueItem($queueName): Check each queue one-by-one. Claim their front-most task (if any)
 *    and then execute it (via PipePool).
 * 3. finishInterval(): Sleep for a moment in between scans. Ensure that we don't run more than 1
 *    scan per POLL_INTERVAL.
 * 4. Go back to step #1.
 *
 * This loop begins with a call to `start()` and terminates with a call to `stop()`.
 * When you call `stop()`, the current loop will wrap-up before finishing.
 */
class CiviQueueWatcher {

  /**
   * How frequently to poll for tasks.
   *
   * Note that there may be multiple queues to poll, and each poll operation may take
   * some #milliseconds. The
   */
  const POLL_INTERVAL = 1.0;

  /**
   * @var \Civi\Citges\Configuration
   */
  protected $config;

  /**
   * FIXME: Shouldn't we be restrting the ctl connection periodically?
   * Maybe it should build on a PipePool of maxWorkers=1?
   *
   * @var \Civi\Citges\CiviPipeConnection
   */
  protected $ctl;

  /**
   * @var \Civi\Citges\PipePool
   */
  protected $pipePool;

  /**
   * @var \Monolog\Logger
   */
  protected $logger;

  /**
   * @var \React\EventLoop\TimerInterface
   */
  protected $timer;

  /**
   * @var \Clue\React\Mq\Queue
   */
  protected $q;

  /**
   * @var \React\Promise\Deferred
   */
  protected $moribundDeferred;

  /**
   * @param \Civi\Citges\Configuration $config
   * @param \Civi\Citges\CiviPipeConnection $ctl
   * @param \Civi\Citges\PipePool $pipePool
   * @param \Monolog\Logger $logger
   */
  public function __construct(Configuration $config, CiviPipeConnection $ctl, PipePool $pipePool, \Monolog\Logger $logger) {
    $this->config = $config;
    $this->ctl = $ctl;
    $this->pipePool = $pipePool;
    $this->logger = $logger;
  }

  public function start(): PromiseInterface {
    $this->lastFillTime = NULL;
    $this->addStep = new \Clue\React\Mq\Queue(1, NULL, function ($args) {
      return $this->onNextStep($args);
    });
    return $this->addStep(['fillSteps']);
  }

  public function stop(): PromiseInterface {
    $this->moribundDeferred = new Deferred();
    return $this->moribundDeferred->promise();
  }

  protected function addStep($step): PromiseInterface {
    return call_user_func($this->addStep, $step);
  }

  protected function fillSteps(): PromiseInterface {
    if ($this->moribundDeferred) {
      $this->addStep = NULL;
      $this->lastFillTime = NULL;
      $this->moribundDeferred->resolve();
      return resolve();
    }
    $this->lastFillTime = microtime(1);
    return $this->ctl->api4('Queue', 'get', ['where' => [['is_autorun', '=', TRUE]]])
      ->then(function ($queues) {
        foreach ($queues as $queue) {
          $this->addStep(['runQueueItem', $queue['name']]);
        }
        $this->addStep(['finishInterval']);
        $this->addStep(['fillSteps']);
      });
  }

  protected function runQueueItem(string $queueName): PromiseInterface {
    return $this->ctl
      ->api4('Queue', 'claimItem', ['queue' => $queueName])
      ->then(function ($items) {
        // claimItem is specified to return 0 or 1 items.
        if (empty($items)) {
          return resolve();
        }

        $item = array_shift($items);
        fprintf(STDERR, "FIXME: Run %s via PipePool\n", print_r($item, 1));
        return $this->ctl->api4('Queue', 'deleteItem', [
          'item' => $item,
        ]);
      });
  }

  /**
   * After polling the Civi queue(s), we may need to sleep a moment
   * before polling again.
   *
   * @return \React\Promise\PromiseInterface
   */
  protected function finishInterval(): PromiseInterface {
    $now = microtime(1);
    $nextFillTime = $this->lastFillTime + static::POLL_INTERVAL;
    $waitTime = $nextFillTime - $now;
    return $waitTime > 0 ? React\Promise\Timer\sleep($waitTime) : resolve();
  }

  protected function onNextStep(array $args): PromiseInterface {
    $verb = array_shift($args);
    switch ($verb) {
      case 'fillSteps':
      case 'runQueueItem':
      case 'finishInterval':
        return $this->{$verb}(...$args);

      break;

      default:
        return reject(new \Exception('Invalid item in task loop: ' . $verb));
    }
  }

}
