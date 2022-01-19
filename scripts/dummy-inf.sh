#!/bin/bash
echo '{"Civi::pipe":{"v":"5.47.alpha1","t":"trusted","d":"dummy-inf.sh"}}'

i=0
while true ; do
  if read LINE; then
    ((i=i+1))
    echo "processed request #$i ($LINE)"
  else
    exit
  fi
done
