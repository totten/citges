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

  public function testHello() {
    $tester = $this->execute('run', [
      '--pipe' => $this->getPath('scripts/dummy-jsonrpc.php'),
      '--define' => ['maxTotalDuration=3'],
    ]);
    $this->assertEquals(0, $tester->getStatusCode());
    $this->assertRegExp(';Setup channels \(control=pipe, work=pipe\);', $tester->getDisplay());
  }

  public function testAmbiguousChannels() {
    try {
      $this->execute('run', [
        '--web' => 'https://example.com',
        '--pipe' => $this->getPath('scripts/dummy-jsonrpc.php'),
        '--define' => ['maxTotalDuration=3'],
      ]);
      $this->fail('Expected exception!');
    }
    catch (\RuntimeException $e) {
      $this->assertExceptionMessage(';Please set --channel options;', $e);
    }
  }

  protected function assertExceptionMessage($messageRegex, \Throwable $t) {
    $matches = (bool) preg_match($messageRegex, $t->getMessage());
    if (!$matches) {
      // Let the exception bubble up - so it's easier to see what went wrong...
      throw $t;
    }
    $this->assertTrue($matches);
  }

}
