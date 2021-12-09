<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 */
class LongChainTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 4,
      // Note: Our quota allows multiple concurrent workers, but our chaining means that we never
      // have concurrent requests. In reality, we only use 1 worker at a time.
      'maxRequests' => 3,
      // Note: We will hit the maxRequests limit. It will force us to replace the worker.
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
