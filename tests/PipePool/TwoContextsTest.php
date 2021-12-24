<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * A single worker handles 8 tasks across 2 contexts (A-B).
 *
 * The worker can service multiple requests for the same context without resetting.
 * However, whenever a request comes for a new context, we must reset the worker.
 *
 * @group unit
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
      // Worker boots and handles context A. Three tasks in a row; same life.
      $pool->dispatch('A', 'Apple 100'),
      $pool->dispatch('A', 'Apple 200'),
      $pool->dispatch('A', 'Apple 300'),

      // Need to kill/restart with a new worker for context B.
      $pool->dispatch('B', 'Banana 100'),
      $pool->dispatch('B', 'Banana 200'),
      $pool->dispatch('B', 'Banana 300'),

      // Need to kill/restart with another worker for context A.... and then B...
      $pool->dispatch('A', 'Apple 400'),
      $pool->dispatch('B', 'Banana 400'),
    ];
  }

  protected function checkResults(array $results): void {
    $expected = [
      // Worker boots and handles context A. Three tasks in a row; same life.
      "processed request #1 (Apple 100)",
      "processed request #2 (Apple 200)",
      "processed request #3 (Apple 300)",

      // Need to kill/restart with a new worker for context B.
      "processed request #1 (Banana 100)",
      "processed request #2 (Banana 200)",
      "processed request #3 (Banana 300)",

      // Need to kill/restart with another worker for context A.... and then B...
      "processed request #1 (Apple 400)",
      "processed request #1 (Banana 400)",
    ];
    $this->assertEquals($expected, $results);
  }

}
