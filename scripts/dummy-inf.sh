#!/bin/bash
echo '{"Civi::pipe":"0.1"}'

i=0
while true ; do
  if read LINE; then
    ((i=i+1))
    echo "processed request #$i ($LINE)"
  else
    exit
  fi
done
