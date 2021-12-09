<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 */
class SomeChainsTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 4,
      'maxRequests' => 100,
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
          return 'Completed A';
        }),
      $pool->dispatch('B', 'Banana 100')
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #1 (Banana 100)', $resp);
          return $pool->dispatch('B', 'Banana 200');
        })
        ->then(function($resp) use ($pool) {
          $this->assertEquals('processed request #2 (Banana 200)', $resp);
          return 'Completed B';
        }),
    ];
  }

  protected function checkResults(array $results): void {
    $expected = ['Completed A', 'Completed B'];
    sort($results);
    $this->assertEquals($expected, $results);
  }

}
