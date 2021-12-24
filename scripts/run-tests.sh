#!/bin/bash
set -e
ROOT="$1"
if [ -z "$ROOT" -o ! -d "$ROOT" ]; then
  echo "Failed to find <civicrm-root>" 2>&1
  echo "usage: $0 <civicrm-root> [...phpunit-args...]" 2>&1
  exit 1
fi
shift

set -x

#phpunit8 --group unit "$@"
CITGES_DEFAULT_PIPE="cv --cwd='$ROOT' ev 'Civi::pipe();'" phpunit8 --group e2e "$@"
