<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * Create several workers. Each runs only a single task.
 */
class ShortWorkerTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 100,
      'maxRequests' => 1,
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
      "dummy-inf: processed request #1 (first)",
      "dummy-inf: processed request #1 (second)",
      "dummy-inf: processed request #1 (third)",
    ], $results);
  }

}
