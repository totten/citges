<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * Create two workers and execute several tasks with each.
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
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertCount(5, $results);

    $resultValues = preg_replace(';processed request #(\d+) \((.*)\);', '\2', $results);
    $this->assertEquals(['first', 'second', 'third', 'fourth', 'fifth'], $resultValues);

    $requestIds = preg_replace(';processed request #(\d+) \((.*)\);', '\1', $results);
    $this->assertDistributionPattern([
      ['1', '2', '3', '1', '2'],
      ['1', '2', '3', '4', '1'],
    ], $requestIds);
  }

}
