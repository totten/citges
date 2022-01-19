<?php

namespace Civi\Coworker\Util;

use Civi\Coworker\Exception\JsonRpcMethodException;
use Civi\Coworker\Exception\JsonRpcProtocolException;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;

class JsonRpc {

  public static function createRequest(string $method, array $params = [], $id = NULL): string {
    return json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => $id]);
  }

  public static function parseResponse(string $responseLine, $id = NULL, array $request = []): PromiseInterface {
    $decode = json_decode($responseLine, TRUE);
    if (!isset($decode['jsonrpc']) || $decode['jsonrpc'] !== '2.0') {
      return reject(new JsonRpcProtocolException("Protocol error: Response lacks JSON-RPC header."));
    }
    if (!array_key_exists('id', $decode) || $decode['id'] !== $id) {
      return reject(new JsonRpcProtocolException("Protocol error: Received response for wrong request."));
    }

    if (array_key_exists('error', $decode) && !array_key_exists('result', $decode)) {
      return reject(new JsonRpcMethodException($decode, $request));
    }
    if (array_key_exists('result', $decode) && !array_key_exists('error', $decode)) {
      return resolve($decode['result']);
    }
    return reject(new JsonRpcProtocolException("Protocol error: Response must include 'result' xor 'error'."));

  }

}
