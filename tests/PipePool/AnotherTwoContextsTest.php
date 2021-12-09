<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 */
class AnotherTwoContextsTest extends PipePoolTestCase {

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
      $pool->dispatch('B', 'fourth'),
      $pool->dispatch('B', 'fifth'),
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertCount(5, $results);

    $resultValues = preg_replace(';processed request #(\d+) \((.*)\);', '\2', $results);
    $this->assertEquals(['first', 'second', 'third', 'fourth', 'fifth'], $resultValues);

    $requestIds = preg_replace(';processed request #(\d+) \((.*)\);', '\1', $results);
    $this->assertDistributionPattern([
      [/*A*/ '1', '2', /*A'*/ '1', /*B*/ '1', '2'],
    ], $requestIds);
  }

}
