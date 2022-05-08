#!/usr/bin/env php
<?php
ini_set('display_errors', 1);
if (PHP_SAPI !== 'cli') {
  printf("coworker is a command-line tool. It is designed to run with PHP_SAPI \"%s\". The active PHP_SAPI is \"%s\".\n", 'cli', PHP_SAPI);
  printf("TIP: In a typical shell environment, the \"php\" command should execute php-cli - not php-cgi or similar.\n");
  exit(1);
}
if (version_compare(PHP_VERSION, '7.2', '<')) {
  echo "coworker requires PHP 7.2+\n";
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

  $cmd = 'bash ' . escapeshellarg(__DIR__ . '/dummy-inf.sh');
  //$cmd = 'bash ' . escapeshellarg(__DIR__ . '/dummy-3.sh');

  $pipe = new \Civi\Coworker\PipeConnection(new \Civi\Coworker\Configuration($cmd));
  $pipe->start()->then(function($welcome) use ($pipe) {
    echo "0. Welcomed with \"$welcome\"\n";
    return $pipe->run('first');
  })->then(function ($line) use ($pipe) {
    echo "1. Received \"$line\"\n";
  })->then(function () use ($pipe) {
    return $pipe->run('second');
  })->then(function ($line) use ($pipe) {
    echo "2. Received \"$line\"\n";
  })->then(function () use ($pipe) {
    $r = $pipe->run('third');
    // $pipe->stop();
    return $r;
  })->then(function ($line) use ($pipe) {
    echo "3. Received \"$line\"\n";
  })->then(function () use ($pipe) {
    $pipe->stop();
  });
}
main();
main();