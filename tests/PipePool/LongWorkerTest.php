<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * Create one worker and execute several tasks with it.
 *
 * @group unit
 */
class LongWorkerTest extends PipePoolTestCase {

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
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertEquals([
      "processed request #1 (first)",
      "processed request #2 (second)",
      "processed request #3 (third)",
    ], $results);
  }

}
