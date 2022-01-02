<?php

namespace Civi\Citges\Exception;

class JsonRpcMethodException extends \Exception {

  /**
   * @var array
   * @readonly
   */
  public $raw;

  public function __construct(array $jsonRpcError) {
    parent::__construct($jsonRpcError['error']['message'] ?? "Unknown JSON-RPC error",
      $jsonRpcError['error']['code'] ?? 0
    );
    $this->raw = $jsonRpcError;
  }

}
