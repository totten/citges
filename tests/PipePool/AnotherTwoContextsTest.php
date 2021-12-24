<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\PipePool;

/**
 * @group unit
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
      $pool->dispatch('A', 'first'), /* A-main */
      $pool->dispatch('A', 'second'), /* A-alt */
      $pool->dispatch('A', 'third'), /* A-main... or maybe A-alt... it's academic... */
      $pool->dispatch('B', 'fourth'), /* B-main */
    ];
  }

  protected function checkResults(array $results): void {
    $this->assertCount(4, $results);

    $resultValues = preg_replace(';processed request #(\d+) \((.*)\);', '\2', $results);
    $this->assertEquals(['first', 'second', 'third', 'fourth'], $resultValues);

    $requestIds = preg_replace(';processed request #(\d+) \((.*)\);', '\1', $results);
    $this->assertDistributionPattern([
      [/*A-main*/ '1', '2', /*A-alt*/ '1', /*B-main*/ '1'],
    ], $requestIds);
  }

}
