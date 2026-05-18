#!/bin/bash

CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2 + $4}')
MEM_USAGE=$(free | awk '/Mem/{printf("%.2f"), $3/$2*100}')
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | tr -d '%')

if (( $(echo "$CPU_USAGE < 85.0" | bc -l) )) && \
   (( $(echo "$MEM_USAGE < 90.0" | bc -l) )) && \
   (( $DISK_USAGE < 90 )); then
  echo "OK"
else
  echo "ALERT"
  echo "CPU: $CPU_USAGE% | MEM: $MEM_USAGE% | DISK: $DISK_USAGE%"
fi

