<?php

namespace Civi\Coworker\Command;

use Civi\Coworker\CoworkerTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class RunCommandUnitTest extends TestCase {

  use CoworkerTestTrait;

  protected function setUp(): void {
    parent::setUp();
  }

  public function testStartup() {
    $tester = $this->execute('run', [
      '--pipe' => $this->getPath('scripts/dummy-jsonrpc.php'),
      '--define' => ['maxTotalDuration=3'],
    ]);
    $this->assertEquals(0, $tester->getStatusCode());

    $expectSequence = [
      ';Starting.*CiviQueueWatcher;',
      ';Poll queues.*CiviQueueWatcher;',
      ';Poll queues.*CiviQueueWatcher;',
      ';Stopping.*CiviQueueWatcher;',
      ';Stopped;',
    ];
    $this->assertRegexpSequence($expectSequence, explode("\n", $tester->getDisplay()));
  }

  // public function testAmbiguousChannels() {
  //   try {
  //     $this->execute('run', [
  //       '--web' => 'https://example.com',
  //       '--pipe' => $this->getPath('scripts/dummy-jsonrpc.php'),
  //       '--define' => ['maxTotalDuration=3'],
  //     ]);
  //     $this->fail('Expected exception!');
  //   }
  //   catch (\RuntimeException $e) {
  //     $this->assertExceptionMessage(';Please set --channel options;', $e);
  //   }
  // }

  // protected function assertExceptionMessage($messageRegex, \Throwable $t) {
  //   $matches = (bool) preg_match($messageRegex, $t->getMessage());
  //   if (!$matches) {
  //     // Let the exception bubble up - so it's easier to see what went wrong...
  //     throw $t;
  //   }
  //   $this->assertTrue($matches);
  // }

  /**
   * @param array $expectSequence
   *   An ordered list of regular expressions.
   *   Each item in the list should appear as part of the sequence, with an equivalent ordering.
   *   Non-matching strings may appear in the list - they will be ignored.
   * @param array $actualLines
   *   List of strings
   */
  private function assertRegexpSequence(array $expectSequence, array $actualLines): void {
    $expectSequenceId = 0;
    foreach ($actualLines as $line) {
      if (preg_match($expectSequence[$expectSequenceId], $line)) {
        $expectSequenceId++;
        if ($expectSequenceId === count($expectSequence)) {
          break;
        }
      }
    }
    if ($expectSequenceId !== count($expectSequence)) {
      $this->fail(sprintf("Missing expected step %d (%s)", $expectSequenceId, $expectSequence[$expectSequenceId]));
    }
  }

}
