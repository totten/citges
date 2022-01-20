<?php

namespace Civi\Coworker\PipePool;

use Civi\Coworker\PipePool;

/**
 * 8 tasks across 5 contexts (A-E; 1-2 tasks per context). Limit of 5 concurrent workers.
 *
 * In this usage pattern, we effectively spin-up 1 worker for each context.
 *
 * @group unit
 */
class ManyContextsTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxConcurrentWorkers' => 5,
      'maxWorkerRequests' => 100,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      // First wave
      $pool->dispatch('A', 'Apple 100'),
      $pool->dispatch('B', 'Banana 100'),
      $pool->dispatch('C', 'Cherry 100'),
      $pool->dispatch('D', 'Date 100'),
      $pool->dispatch('E', 'Eggplant 100'),

      // Second wave
      $pool->dispatch('C', 'Cherry 200'), /* Re-use C worker */
      $pool->dispatch('D', 'Date 200'), /* Re-use D worker */
      $pool->dispatch('A', 'Apple 200'), /* Re-use A worker */
    ];
  }

  protected function checkResults(array $results): void {
    $expected = [
      // A worker
      "processed request #1 (Apple 100)",
      "processed request #2 (Apple 200)",
      // B worker
      "processed request #1 (Banana 100)",
      // C worker
      "processed request #1 (Cherry 100)",
      "processed request #2 (Cherry 200)",
      // D worker
      "processed request #1 (Date 100)",
      "processed request #2 (Date 200)",
      // E worker
      "processed request #1 (Eggplant 100)",
    ];
    sort($results);
    sort($expected);
    $this->assertEquals($expected, $results);
  }

}
