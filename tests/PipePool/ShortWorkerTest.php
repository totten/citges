<?php

namespace Civi\Coworker\PipePool;

use Civi\Coworker\PipePool;

/**
 * Create several workers. Each runs only a single task.
 * @group unit
 */
class ShortWorkerTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxConcurrentWorkers' => 100,
      'maxWorkerRequests' => 1,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      $pool->dispatch('A', 'first'),
      $pool->dispatch('A', 'second'),
      $pool->dispatch('A', 'third'),
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertEquals([
      "processed request #1 (first)",
      "processed request #1 (second)",
      "processed request #1 (third)",
    ], $results);
  }

}
