<?php

namespace Civi\Citges\PipePool;

use Civi\Citges\CitgesTestTrait;
use Civi\Citges\Configuration;
use Civi\Citges\PipePool;
use Civi\Citges\Util\FileUtil;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use function React\Promise\all;

abstract class PipePoolTestCase extends TestCase {

  use CitgesTestTrait;

  /**
   * @var \Monolog\Handler\TestHandler
   */
  protected $log;

  protected $flags;

  protected function setUp(): void {
    parent::setUp();
    $this->log = new TestHandler();
    $this->flags = [];
  }

  abstract protected function buildConfig(): array;

  abstract protected function buildPromises(PipePool $pool): array;

  protected function checkResults(array $results): void {
  }

  protected function checkLog(TestHandler $log): void {
  }

  public function testPool() {
    $pool = $this->createPool($this->buildConfig());
    try {
      $this->assertEquals($pool, $this->await($pool->start()));
      $promises = $this->buildPromises($pool);
      $results = $this->await(all($promises));
      $this->checkResults($results);
      $this->checkLog($this->log);
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
