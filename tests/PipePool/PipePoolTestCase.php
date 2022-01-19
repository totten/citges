<?php

namespace Civi\Coworker\PipePool;

use Civi\Coworker\CoworkerTestTrait;
use Civi\Coworker\Configuration;
use Civi\Coworker\PipePool;
use Civi\Coworker\Util\FileUtil;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use function React\Promise\all;

abstract class PipePoolTestCase extends TestCase {

  use CoworkerTestTrait;

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

    $class = explode('\\', static::CLASS);
    $id = array_pop($class);
    $logFile = FileUtil::put($this->getPath('tmp/' . $id . '.txt'), '');
    $log->pushHandler(new StreamHandler($logFile));

    $pool = new PipePool($cfg, $log);
    return $pool;
  }

  protected function assertDistributionPattern($validOptions, $actual) {
    sort($actual);
    $match = FALSE;
    foreach ($validOptions as $validOption) {
      sort($validOption);
      if ($validOption === $actual) {
        $match = TRUE;
        break;
      }
    }
    $this->assertTrue($match,
      "List should match an valid distribution pattern. (Actual pattern: " . implode(',', $actual) . ")"
    );
  }

}
