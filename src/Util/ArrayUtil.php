<?php

namespace Civi\Coworker\Util;

class ArrayUtil {

  public static function tally(array $values): array {
    return array_reduce($values,
      function ($idx, $value) {
        $idx[$value] = 1 + ($idx[$value] ?? 0);
        return $idx;
      },
      []);
  }

  public static function countRegex(string $pattern, array $values) {
    return count(preg_grep($pattern, $values));
  }

  public static function groupRegex(string $pattern, array $values) {
    $groups = [];
    foreach ($values as $value) {
      $group = preg_replace($pattern, '\1', $value);
      $groups[$group][] = $value;
    }
    return $groups;
  }

  public static function isSubset($big, $small) {
    $intersect = array_intersect($big, $small);
    return count($intersect) === count($small);
  }

}