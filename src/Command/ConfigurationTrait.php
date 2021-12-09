<?php

namespace Civi\Citges\Command;

use Civi\Citges\Configuration;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfigurationTrait {

  public function configureCommonOptions() {
    $this->addOption('log', NULL, InputOption::VALUE_REQUIRED, 'Log file');
    $this->addOption('log-level', NULL, InputOption::VALUE_REQUIRED, 'Level of information to write to log file. If omitted, choose based on general verbosity. (debug|info|notice|warning|error|critical|alert|emergency)');
    $this->addOption('log-format', NULL, InputOption::VALUE_REQUIRED, 'How to format log info (text|json)', 'text');
  }

  protected function createConfiguration(InputInterface $input, OutputInterface $output): Configuration {
    // Wouldn't it be nicer to have some attribute/annotation mapping...

    $map = [
      'log' => 'logFile',
      'log-level' => 'logLevel',
      'log-format' => 'logFormat',
    ];

    $cfg = new Configuration();
    foreach ($map as $inputOption => $cfgOption) {
      $inputValue = $input->getOption($inputOption);
      if ($inputValue !== '') {
        $cfg->{$cfgOption} = $inputValue;
      }
    }

    if (empty($cfg->logLevel)) {
      if ($output->isVeryVerbose()) {
        $cfg->logLevel = 'debug';
      }
      elseif ($output->isVerbose()) {
        $cfg->logLevel = 'info';
      }
      else {
        $cfg->logLevel = 'warning';
      }
    }

    return $cfg;
  }

  protected function createLogger(InputInterface $input, OutputInterface $output, Configuration $config): Logger {
    $log = new \Monolog\Logger($this->getName());

    $formatter = $config->logFormat === 'json' ? new JsonFormatter() : new LineFormatter();

    if ($config->logFile) {
      $fileHandler = new StreamHandler($config->logFile, $config->logLevel);
      $fileHandler->setFormatter($formatter);
      $log->pushHandler($fileHandler);
    }

    if ($output->isVerbose() || !$config->logFile) {
      $consoleHandler = new StreamHandler(STDERR, $config->logLevel);
      $consoleHandler->setFormatter($formatter);
      $log->pushHandler($consoleHandler);
    }

    $log->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
    return $log;
  }

}
