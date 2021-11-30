<?php

namespace Civi\Citges\Util;

use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;

class ProcessUtil {

  const INTERVAL = 0.1;

  use ChattyTrait;

  /**
   * Shutdown a process. If it doesn't shutdown gracefully, escalate through more
   * aggressive formulations (close STDIN; send SIGTERM; send SIGKILL).
   *
   * @param \React\ChildProcess\Process $process
   * @param float $timeout
   *   terminateWithEscalation() will initially try a gentle shutdown by closing STDIN.
   *   If the process doesn't end within $forceTimeout seconds, it will escalate to SIGTERM.
   *   If it still doesn't end within next $forceTimeout seconds, it will escalate to SIGKILL.
   *   If it still doesn't end within next $forceTimeout seconds, assume it is totally forgone.
   * @return PromiseInterface
   *   A promise that returns once shutdown is complete.
   */
  public static function terminateWithEscalation(Process $process, float $timeout = 1.5) {
    $stopping = new Deferred();

    $now = microtime(TRUE);
    $escalate = [
      'sigterm' => $now + $timeout,
      'sigkill' => $now + ($timeout * 2),
      'zombie' => $now + ($timeout * 3),
    ];

    $process->stdin->close();
    $timer = Loop::addPeriodicTimer(static::INTERVAL, function () use (&$timer, &$escalate, $stopping, $process) {
      if (!$process->isRunning()) {
        Loop::cancelTimer($timer);
        $stopping->resolve();
        // ^^ At some point, we may want more sophisticated reporting about the escalation, but for now KISS.
        return;
      }

      if (isset($escalate['sigterm'])) {
        if (microtime(TRUE) < $escalate['sigterm']) {
          return;
        }
        static::verbose("Terminate pipe worker\n");
        $process->terminate(defined('SIGTERM') ? SIGTERM : 15);
        unset($escalate['sigterm']);
        return;
      }

      if (isset($escalate['sigkill'])) {
        if (microtime(TRUE) < $escalate['sigkill']) {
          return;
        }
        static::verbose("Kill pipe worker\n");
        $process->terminate(defined('SIGKILL') ? SIGKILL : 9);
        unset($escalate['sigkill']);
      }

      if (isset($escalate['zombie'])) {
        if (microtime(TRUE) < $escalate['zombie']) {
          return;
        }
        Loop::cancelTimer($timer);
        static::verbose("Shutdown of pipe worker has failed\n");
        $stopping->resolve();
        // ^^ At some point, we may want more sophisticated reporting about the escalation, but for now KISS.
      }

      throw new \RuntimeException("Abnormality while stopping process.");
    });

    return $stopping->promise();

  }

}
