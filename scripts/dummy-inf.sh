#!/bin/bash
SELF='dummy-inf'

echo 'Civi::pipe 0.1'

i=0
while true ; do
  if read LINE; then
    ((i=i+1))
    echo "$SELF: processed $i ($LINE)"
  else
    exit
  fi
done
