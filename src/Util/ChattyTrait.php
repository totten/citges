<?php

namespace Civi\Citges\Util;

trait ChattyTrait {

  protected function verbose($msg, ...$args): void {
    $msg = sprintf('[#%d %s] ', posix_getpid(), static::CLASS) . $msg;
    call_user_func('printf', $msg, ...$args);
  }

}
