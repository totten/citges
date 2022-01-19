<?php

namespace Civi\Coworker\Util;

class FunctionUtil {

  /**
   * Wrap a callback in a singleton guard.
   *
   * This will prevent the function from running multiple times.
   *
   * @param callable $callback
   * @param callable $onDuplicate
   *   Alternative action to call if we happen to receive any extra invocations.
   * @return \Closure
   */
  public static function singular($callback, $onDuplicate = NULL) {
    $running = FALSE;
    return function(...$args) use (&$running, $callback, $onDuplicate) {
      if ($running) {
        if ($onDuplicate) {
          $onDuplicate();
        }
        return;
      }

      $running = TRUE;
      try {
        return $callback(...$args);
      }
      finally {
        $running = FALSE;
      }
    };
  }

}
