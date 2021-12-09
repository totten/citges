<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * Jobs are tagged with two different contexts.
 *
 * Even though quotas might allow 1 worker to do all 6 tasks,
 * we will need at least 2 workers - because the contexts must be separate.
 */
class TwoContextsTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 1,
      'maxRequests' => 100,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      $pool->dispatch('A', 'first'),
      $pool->dispatch('A', 'second'),
      $pool->dispatch('A', 'third'),
      $pool->dispatch('B', 'fourth'),
      $pool->dispatch('B', 'fifth'),
      $pool->dispatch('B', 'sixth'),
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertCount(6, $results);

    $resultValues = preg_replace(';processed request #(\d+) \((.*)\);', '\2', $results);
    $this->assertEquals(['first', 'second', 'third', 'fourth', 'fifth', 'sixth'], $resultValues);

    $requestIds = preg_replace(';processed request #(\d+) \((.*)\);', '\1', $results);
    $this->assertDistributionPattern([
      [/*A*/ '1', '2', '3', /*B*/ '1', '2', '3'],
    ], $requestIds);
  }

}
