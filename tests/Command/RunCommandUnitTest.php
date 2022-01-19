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
      '--pipe' => 'echo',
    ]);
    $this->assertEquals(0, $tester->getStatusCode());
    $this->assertRegExp(';Setup channels \(control=pipe, work=pipe\);', $tester->getDisplay());
  }

  public function testAmbiguousChannels() {
    $this->expectExceptionMessageMatches(';Please set --channel options;');
    $this->execute('run', [
      '--web' => 'https://example.com',
      '--pipe' => 'cat /dev/null',
    ]);
  }

}
