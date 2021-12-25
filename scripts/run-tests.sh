#!/bin/bash
set -e
export CV_TEST_BUILD="$1"
if [ -z "$CV_TEST_BUILD" -o ! -d "$CV_TEST_BUILD" ]; then
  echo "Failed to find <civicrm-root>" 2>&1
  echo "usage: $0 <civicrm-root> [...phpunit-args...]" 2>&1
  exit 1
fi
shift

set -x

phpunit8 --group unit "$@"
phpunit8 --group e2e "$@"
