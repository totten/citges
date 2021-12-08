#!/usr/bin/env php
<?php

ini_set('display_errors', 1);
if (PHP_SAPI !== 'cli') {
  printf("citges is a command-line tool. It is designed to run with PHP_SAPI \"%s\". The active PHP_SAPI is \"%s\".\n", 'cli', PHP_SAPI);
  printf("TIP: In a typical shell environment, the \"php\" command should execute php-cli - not php-cgi or similar.\n");
  exit(1);
}
if (version_compare(PHP_VERSION, '7.2', '<')) {
  echo "citges requires PHP 7.2+\n";
  exit(2);
}
$found = 0;
$autoloaders = [
  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
  dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'autoload.php',
];
foreach ($autoloaders as $autoloader) {
  if (file_exists($autoloader)) {
    require_once $autoloader;
    $found = 1;
    break;
  }
}
if (!$found) {
  die("Failed to find autoloader");
}

function main() {
  printf("\n## Run %s %s()\n\n", basename(__FILE__), __FUNCTION__);

  $cfg = new \Civi\Citges\Configuration();
  $cfg->pipeCommand = 'bash ' . escapeshellarg(__DIR__ . '/dummy-inf.sh');
  // $cfg->pipeCommand = 'bash ' . escapeshellarg(__DIR__ . '/dummy-3.sh');

  $log = new \Monolog\Logger(basename(__FILE__));
  $log->pushHandler(new \Monolog\Handler\StreamHandler(STDERR));
  $log->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());

  $pool = new \Civi\Citges\PipePool($cfg, $log);
  $pool->start()
    ->then(function () use ($pool) {
      $all = [];
      for ($n = 0; $n < 2; $n++) {
        $all[] = $pool->dispatch('myctx', "x{$n}::1")
          ->then(function ($responseLine) use ($n) {
            echo "[x{$n}::1] => [$responseLine]\n";
          })
          ->then(function () use ($n, $pool) {
            return $pool->dispatch('myctx', "x{$n}::2");
          })
          ->then(function ($responseLine) use ($n) {
            echo "[x{$n}::2] => [$responseLine]\n";
          });
      }
      return \React\Promise\all($all);
    })
    ->then(function() use ($pool) {
      echo "Shutdown!\n";
      return $pool->stop();
    });
}

main();
