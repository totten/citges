<?php

namespace Civi\Coworker\Util;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class PromiseUtil {

  /**
   * When a promise ($from) completes, pass the outcome to another,
   *
   * Note: PromiseInterface::notify() and PromiseInterface::update() are currently deprecated.
   * Rather than dig deeper into supporting them, we omit support for them.
   *
   * @param \React\Promise\PromiseInterface $from
   * @param \React\Promise\Deferred $to
   * @param callable|null $always
   *
   */
  public static function chain(PromiseInterface $from, Deferred $to, $always = NULL) {
    if ($always === NULL) {
      $from->then([$to, 'resolve'], [$to, 'reject']);
    }
    else {
      $from->then(
        function (...$args) use ($always, $to) {
          $always();
          $to->resolve(...$args);
        },
        function (...$args) use ($always, $to) {
          $always();
          $to->reject(...$args);
        }
      );
    }
  }

  public static function dump(string $message = ''): array {
    return [
      function ($response) use ($message) {
        fwrite(STDERR, $message . print_r(['resp' => $response, 1]) . "\n");
        return $response;
      },
    //      function (\Throwable $err) use ($message) {
    //        if ($err instanceof JsonRpcMethodException) {
    //          fwrite(STDERR, $message . 'Promise failed: ' . print_r($err->raw, 1) . "\n");
    //        }
    //        else {
    //          fwrite(STDERR, $message . sprintf("Promise failed: %s: %s\n%s",
    //            get_class($err), $err->getMessage(), $err->getTraceAsString()));
    //        }
    //      },
    ];
  }

}
