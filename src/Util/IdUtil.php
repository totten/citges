<?php

namespace Civi\Citges\Util;

class IdUtil {

  private static $id = 1;

  public static function next(): int {
    return static::$id++;
  }

}