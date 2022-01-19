#!/usr/bin/env php
<?php

/**
 * @file
 *
 * This is a dummy JSON-RPC listener. All requests yield
 * a formulaic response (`{"msg": "processed request 123"}`)
 */

function fail(...$msg) {
  fprintf(STDERR, ...$msg);
  exit(1);
}

function send($data) {
  echo json_encode($data) . "\n";
}

$PROG = basename(__FILE__);
$maxRequests = $argv[1] ?? NULL;

if (PHP_SAPI !== 'cli') {
  fail("%s must be executed via CLI", $PROG);
}

send([
  'Civi::pipe' => [
    'v' => '5.47.alpha1',
    't' => 'trusted',
    'd' => $PROG,
  ],
]);

$i = 0;
// while ($line = trim(fgets(STDIN))) {
while ($line = fgets(STDIN)) {
  $i++;
  $request = json_decode($line, TRUE);
  send([
    'jsonrpc' => '2.0',
    'result' => ['msg' => 'processed request ' . $i],
    'id' => $request['id'] ?? NULL,
  ]);
  if (is_numeric($maxRequests) && $i >= $maxRequests) {
    break;
  }
}
