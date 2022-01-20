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

function respond($request, $result) {
  send([
    'jsonrpc' => '2.0',
    'result' => $result,
    'id' => $request['id'] ?? NULL,
  ]);
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

  $sig = $request['method'];
  if ($request['method'] === 'api3' || $request['method'] === 'api4') {
    $sig .= ':' . $request['params'][0] . '.' . $request['params'][1];
  }

  switch ($sig) {
    case 'api4:Queue.get':
      respond($request, []);
      break;

    default:
      respond($request, ['msg' => 'processed request ' . $i]);
      break;
  }

  if (is_numeric($maxRequests) && $i >= $maxRequests) {
    break;
  }
}
