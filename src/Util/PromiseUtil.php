<?php

namespace Civi\Citges\Util;

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
        function(...$args) use ($always, $to) {
          $always();
          $to->resolve(...$args);
        },
        function(...$args) use ($always, $to) {
          $always();
          $to->reject(...$args);
        }
      );
    }
  }

}
