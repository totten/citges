<?php

namespace Civi\Coworker\Util;

trait ChattyTrait {

  protected static function verbose($msg, ...$args): void {
    $msg = sprintf('[#%d %s] ', posix_getpid(), static::CLASS) . $msg;
    call_user_func('printf', $msg, ...$args);
  }

}
