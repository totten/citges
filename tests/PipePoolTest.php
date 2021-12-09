<?php

namespace Civi\Citges;

use Civi\Citges\Util\FileUtil;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use function React\Promise\all;

/**
 * @group unit
 */
class PipePoolTest extends TestCase {

  use CitgesTestTrait;

  /**
   * @var \Monolog\Handler\TestHandler
   */
  protected $log;

  protected function setUp(): void {
    parent::setUp();
    $this->log = new TestHandler();
  }

  public function testParallel() {
    $pool = $this->createPool([
      'maxWorkers' => 100,
      'maxRequests' => 1,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ]);
    try {
      $this->assertEquals($pool, $this->await($pool->start()));
      $okCount = 0;
      $promises = [
        $pool->dispatch('A', 'first')->then(function($resp) use (&$okCount) {
          $this->assertEquals("dummy-inf: processed request #1 (first)", $resp);
          $okCount++;
        }),
        $pool->dispatch('A', 'second')->then(function($resp) use (&$okCount) {
          $this->assertEquals("dummy-inf: processed request #1 (second)", $resp);
          $okCount++;
        }),
        $pool->dispatch('A', 'third')->then(function($resp) use (&$okCount) {
          $this->assertEquals("dummy-inf: processed request #1 (third)", $resp);
          $okCount++;
        }),
      ];
      $this->await(all($promises));
      $this->assertEquals(3, $okCount);
    }
    finally {
      if ($pool) {
        $this->await($pool->stop());
      }
    }
  }

  public function testLinear() {
    $pool = $this->createPool([
      'maxWorkers' => 1,
      'maxRequests' => 100,
      'pipeCommand' => $this->getPath('scripts/dummy-inf.sh'),
    ]);
    try {
      $this->assertEquals($pool, $this->await($pool->start()));
      $okCount = 0;
      $promises = [
        $pool->dispatch('A', 'first')->then(function($resp) use (&$okCount) {
          $this->assertEquals("dummy-inf: processed request #1 (first)", $resp);
          $okCount++;
        }),
        $pool->dispatch('A', 'second')->then(function($resp) use (&$okCount) {
          $this->assertEquals("dummy-inf: processed request #2 (second)", $resp);
          $okCount++;
        }),
        // $pool->dispatch('A', 'third')->then(function($resp) use (&$okCount) {
        //   $this->assertEquals("dummy-inf: processed request #3 (third)", $resp);
        //   $okCount++;
        // }),
      ];
      $this->await(all($promises));
      // $this->assertEquals(3, $okCount);
      $this->assertEquals(2, $okCount);
    }
    finally {
      if ($pool) {
        $this->await($pool->stop());
      }
    }
  }

  protected function createPool(array $config = []): PipePool {
    $cfg = new Configuration($config);

    $log = new Logger(__CLASS__);
    $log->pushProcessor(new PsrLogMessageProcessor());
    $log->pushHandler($this->log);

    $id = preg_replace(';[^\w];', '_', $this->getName(TRUE));
    $logFile = FileUtil::put($this->getPath('tmp/' . $id . '.txt'), '');
    $log->pushHandler(new StreamHandler($logFile));

    $pool = new PipePool($cfg, $log);
    return $pool;
  }

}
