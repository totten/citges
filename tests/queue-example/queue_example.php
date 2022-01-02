<?php

function queue_example_reset(string $pattern) {
  CRM_Core_DAO::executeQuery('DELETE FROM civicrm_queue_item WHERE queue_name LIKE %1', [
    1 => [$pattern, 'String'],
  ]);
}

function queue_example_logme($ctx, $logValue) {
  $log = getenv('QUEUE_EXAMPLE_LOG');
  if (!$log || !file_exists($log) || !is_writable($log)) {
    throw new Exception('Undefined variable: QUEUE_EXAMPLE_LOG');
  }
  $msg = json_encode(['t' => time(), 'v' => $logValue]);
  file_put_contents($log, $msg, FILE_APPEND);
}

function queue_example_addlogme(string $queueName, array $logValues) {
  /** @var CRM_Queue_Queue $queue */
  $queue = Civi::queue($queueName);
  foreach ($logValues as $logValue) {
    $queue->createItem(new CRM_Queue_Task('queue_example_logme', [$logValue]));
  }
}
