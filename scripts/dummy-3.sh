#!/bin/bash
echo '{"Civi::pipe":{"v":"5.47.alpha1","t":"trusted","d":"dummy-3.sh"}}'

read A
echo "Thanks for A=$A"

read B
echo "Thanks for B=$B"

read C
sleep 3
echo "Thanks for C=$C"
