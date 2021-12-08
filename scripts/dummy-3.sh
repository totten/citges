#!/bin/bash
SELF='dummy-3'

echo 'Civi::pipe 0.1'

read A
echo "$SELF: Thanks for A=$A"

read B
echo "$SELF: Thanks for B=$B"

read C
sleep 3
echo "$SELF: Thanks for C=$C"
