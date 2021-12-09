<?php
namespace Civi\Citges;

use React\Promise\PromiseInterface;
use Symfony\Component\Console\Tester\CommandTester;

trait CitgesTestTrait {

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

}
