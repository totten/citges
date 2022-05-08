<?php

use Psr\Log\LogLevel;

class LogUtil {

  /**
   * @param string $url
   * @param  Level|LevelName|LogLevel::* $level Level or level name
   * @return \Monolog\Handler\HandlerInterface
   */
  // public static function createLogHandleFromUrl(string $url, $level): \Monolog\Handler\HandlerInterface {
  //   $parts = parse_url($url);
  //
  //   switch ($parts['scheme']) {
  //     case 'file':
  //       $items = [];
  //       if (!empty($parts['host'])) {
  //         $items[] = $parts['host'];
  //       }
  //       if (!empty($parts['path'])) {
  //         $items[] = $parts['path'];
  //       }
  //       $file = \Monolog\Utils::canonicalizePath(implode('/', $items));
  //       return new \Monolog\Handler\StreamHandler($file, $level);
  //
  //     default:
  //       throw new \RuntimeException("Unrecognized log URL: $url");
  //   }
  // }

}