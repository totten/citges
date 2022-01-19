<?php

namespace Civi\Coworker\PipePool;

use Civi\Coworker\PipePool;

/**
 * Two workers handle 7 tasks from the same context.
 *
 * Because all tasks have the same context, we are free to split tasks between them.
 * This is slightly non-deterministic (depending on which workers happen to go faster).
 * Ideally, one worker handles 4 tasks, and the other handles 3 tasks - but this
 * could fluctuate (4+3=7; 5+2=7; 6+1=7).
 *
 * @group unit
 */
class TwoWorkerTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 2,
      'maxRequests' => 100,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      $pool->dispatch('A', 'first'),
      $pool->dispatch('A', 'second'),
      $pool->dispatch('A', 'third'),
      $pool->dispatch('A', 'fourth'),
      $pool->dispatch('A', 'fifth'),
      $pool->dispatch('A', 'sixth'),
      $pool->dispatch('A', 'seventh'),
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertCount(7, $results);

    $resultValues = preg_replace(';processed request #(\d+) \((.*)\);', '\2', $results);
    $this->assertEquals(['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh'], $resultValues);

    $requestIds = preg_replace(';processed request #(\d+) \((.*)\);', '\1', $results);
    $this->assertDistributionPattern([
      // We know that both workers W1 and W2 will handle 1+ tasks. We'd expect an even split given
      // consistent load and comparable tasks - but the exact split could vary.
      [/*W1*/ '1', '2', '3', '4', /*W2*/ '1', '2', '3'],
      [/*W1*/ '1', '2', '3', '4', '5', /*W2*/ '1', '2'],
      [/*W1*/ '1', '2', '3', '4', '5', '6', /*W2*/ '1'],
    ], $requestIds);
  }

}
