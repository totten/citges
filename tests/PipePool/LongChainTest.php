<?php

namespace Civi\Coworker\PipePool;

use Civi\Coworker\PipePool;

/**
 * 5 tasks across 1 context with a limit of 4 concurrent workers.
 *
 * We never use the full limit of 4 concurrent workers because the tasks are organized
 * as a linear chain - so we only need 1 worker at a time.
 *
 * In theory, 1 worker could handle all 5 requests. However, due to maxRequests=3,
 * the first worker will be replaced after 3 requests.
 *
 * @group unit
 */
class LongChainTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 4,
      'maxRequests' => 3,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      $pool->dispatch('A', 'Apple 100')
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #1 (Apple 100)', $resp);
          return $pool->dispatch('A', 'Apple 200');
        })
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #2 (Apple 200)', $resp);
          return $pool->dispatch('A', 'Apple 300');
        })
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #3 (Apple 300)', $resp);
          return $pool->dispatch('A', 'Apple 400');
        })
        // Per maxRequests, that was the last time we could reuse the worker! New worker started.
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #1 (Apple 400)', $resp);
          return $pool->dispatch('A', 'Apple 500');
        })
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #2 (Apple 500)', $resp);
          return 'Completed A';
        }),
    ];
  }

  protected function checkResults(array $results): void {
    $expected = ['Completed A'];
    $this->assertEquals($expected, $results);
  }

}
