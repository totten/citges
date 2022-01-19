<?php

namespace Civi\Coworker\Exception;

class JsonRpcMethodException extends \Exception {

  const CUTOFF = 100;

  /**
   * @var array
   * @readonly
   */
  public $raw;

  public function __construct(array $jsonRpcError, ?array $request = NULL) {
    $parts = [];

    if (isset($jsonRpcError['error']['message'])) {
      $parts[] = sprintf('JSON-RPC Method Exception: %s', $jsonRpcError['error']['message']);
    }
    if (isset($request['method'])) {
      $args = json_encode($request['params'] ?? []);
      if (strlen($args) > static::CUTOFF) {
        $args = substr($args, 0, static::CUTOFF) . '...';
      }
      $parts[] = sprintf('Call to: %s%s: ', $request['method'], $args);
    }
    if (isset($request['caller'])) {
      $parts[] = sprintf('Call from: %s', $request['caller']);
    }
    // $parts[] = ($jsonRpcError['error']['message'] ?? 'Unknown JSON-RPC error');

    parent::__construct(implode("\n", $parts),
      $jsonRpcError['error']['code'] ?? 0
    );
    $this->raw = $jsonRpcError;
  }

}
