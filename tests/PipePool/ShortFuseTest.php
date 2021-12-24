<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * Create workers who can only handle 2 jobs each.
 * @group unit
 */
class ShortFuseTest extends PipePoolTestCase {

  protected function buildConfig(): array {
    return [
      'maxWorkers' => 2,
      'maxRequests' => 2,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ];
  }

  protected function buildPromises(PipePool $pool): array {
    return [
      $pool->dispatch('A', 'first'),
      $pool->dispatch('A', 'second'),
      $pool->dispatch('A', 'third'),
      $pool->dispatch('A', 'fourth'),
      $pool->dispatch('A', 'fifth'),
      $pool->dispatch('A', 'sixth'),
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertCount(6, $results);

    $resultValues = preg_replace(';processed request #(\d+) \((.*)\);', '\2', $results);
    $this->assertEquals(['first', 'second', 'third', 'fourth', 'fifth', 'sixth'], $resultValues);

    $requestIds = preg_replace(';processed request #(\d+) \((.*)\);', '\1', $results);
    $this->assertDistributionPattern([
      ['1', '2', '1', '2', '1', '2'],
      ['1', '2', '1', '2', '1', '1'],
    ], $requestIds);
  }

}
