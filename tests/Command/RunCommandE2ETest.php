<?php

namespace Civi\Citges\Command;

use Civi\Citges\CitgesTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group e2e
 */
class RunCommandE2ETest extends TestCase {

  use CitgesTestTrait;

  protected function setUp(): void {
    if (!getenv('CITGES_DEFAULT_PIPE')) {
      $this->fail('E2E tests require a default pipe command. (Ex: CITGES_DEFAULT_PIPE="cv --cwd=\'/path/to/civi\' ev \'Civi::pipe();\'")');
    }
    parent::setUp();
  }

  public function testQueue() {
    $this->fail('todo');
  }

}
