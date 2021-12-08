<?php
namespace Civi\Citges;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    $application = new Application('citges', '@package_version@');
    $application->run();
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->addCommands($this->createCommands());
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultInputDefinition() {
    $definition = parent::getDefaultInputDefinition();
    $definition->addOption(new InputOption('cwd', NULL, InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'));
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    $workingDir = $input->getParameterOption(array('--cwd'));
    if (FALSE !== $workingDir && '' !== $workingDir) {
      if (!is_dir($workingDir)) {
        throw new \RuntimeException("Invalid working directory specified, $workingDir does not exist.");
      }
      if (!chdir($workingDir)) {
        throw new \RuntimeException("Failed to use directory specified, $workingDir as working directory.");
      }
    }
    return parent::doRun($input, $output);
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands($context = 'default') {
    $commands = array();
    $commands[] = new \Civi\Citges\Command\RunCommand();
    return $commands;
  }

}
