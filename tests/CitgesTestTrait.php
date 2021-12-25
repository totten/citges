<?php
namespace Civi\Citges;

use React\Promise\PromiseInterface;
use Symfony\Component\Console\Tester\CommandTester;

trait CitgesTestTrait {

  protected function setupE2E() {
    $copyIfNew = function($from, $to) {
      if (!file_exists($to) || filemtime($from) > filemtime($to)) {
        copy($from, $to);
      }
    };

    $extDir = $this->cv('path -c extensionsDir');
    $myExtDir = "$extDir/queue-example";
    if (!is_dir($myExtDir)) {
      mkdir($myExtDir);
    }
    $copyIfNew(__DIR__ . '/queue-example/info.xml', "$myExtDir/info.xml");
    $copyIfNew(__DIR__ . '/queue-example/queue_example.php', "$myExtDir/queue_example.php");

    $this->cv('en queue_example');
  }

  /**
   * Create a helper for executing command-tests in our application.
   *
   * @param string $commandName
   * @param array $args must include key "command"
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  public function execute(string $commandName, array $args = []): \Symfony\Component\Console\Tester\CommandTester {
    $args = array_merge([
      'command' => $commandName,
      '--log-level' => 'debug',
      '--log-format' => 'json',
      '--verbose' => TRUE,
    ], $args);
    $application = new Application();
    $command = $application->find($args['command']);
    $commandTester = new CommandTester($command);
    $commandTester->execute($args);
    return $commandTester;
  }

  public function getPath(?string $relFile = NULL): string {
    $dir = dirname(__DIR__);
    return $relFile ? "$dir/$relFile" : $dir;
  }

  protected function await(PromiseInterface $promise) {
    return \Clue\React\Block\await($promise, NULL, 120);
  }

  protected function cv(string $cmd): string {
    $cvCmd = $this->cvCmd($cmd);
    $result = system($cvCmd, $exitCode);
    if ($exitCode !== 0) {
      throw new \RuntimeException("Command failed: $cvCmd\n$result\n");
    }
    return $result;
  }

  protected function cvEval(string $phpCode): string {
    return $this->cv('ev ' . escapeshellarg($phpCode));
  }

  /**
   * @param $cmd
   *
   * @return string
   */
  protected function cvCmd(string $cmd): string {
    $cvRoot = getenv('CV_TEST_BUILD');
    $this->assertTrue(is_dir($cvRoot));
    $cvCmd = sprintf('cv --cwd=%s %s', escapeshellarg($cvRoot), $cmd);
    return $cvCmd;
  }

}
