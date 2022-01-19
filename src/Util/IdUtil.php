<?php

namespace Civi\Coworker\Util;

class IdUtil {

  private static $id = [];

  public static function next(string $set = ''): int {
    static::$id[$set] = 1 + (static::$id[$set] ?? 0);
    return static::$id[$set];
  }

}
