<?php

namespace Civi\Citges\Command;

use Civi\Citges\CitgesTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class RunCommandUnitTest extends TestCase {

  use CitgesTestTrait;

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
