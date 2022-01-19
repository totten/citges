<?php

namespace Civi\Coworker\Util;

class FileUtil {

  /**
   * Create a file.
   * Make dir if needed.
   * Overwrite if existing.
   *
   * @param string $file
   * @param string $content
   * @return string
   *   File name
   */
  public static function put(string $file, string $content): string {
    if (!is_dir(dirname($file))) {
      mkdir(dirname($file), 0777, TRUE);
    }
    file_put_contents($file, $content);
    return $file;
  }

}
