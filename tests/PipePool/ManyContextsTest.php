<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * There are 5 tasks. Going by quotas, there are many ways they could be distributed.
 * However, each task has a different context -- so each must run in a separate worker.
 */
class ManyContextsTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 5,
      'maxRequests' => 100,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      $pool->dispatch('A', 'Apple 100'),
      $pool->dispatch('B', 'Banana 100'),
      $pool->dispatch('C', 'Cherry 100'),
      $pool->dispatch('D', 'Date 100'),
      $pool->dispatch('E', 'Eggplant 100'),
      $pool->dispatch('C', 'Cherry 200'),
      $pool->dispatch('D', 'Date 200'),
      $pool->dispatch('A', 'Apple 200'),
    ];
  }

  protected function checkResults(array $results): void {
    $expected = [
      "processed request #1 (Apple 100)",
      "processed request #2 (Apple 200)",
      "processed request #1 (Banana 100)",
      "processed request #1 (Cherry 100)",
      "processed request #2 (Cherry 200)",
      "processed request #1 (Date 100)",
      "processed request #1 (Eggplant 100)",
      "processed request #2 (Date 200)",
    ];
    sort($results);
    sort($expected);
    $this->assertEquals($expected, $results);
  }

}
