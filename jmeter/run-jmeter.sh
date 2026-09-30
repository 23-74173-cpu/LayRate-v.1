#!/usr/bin/env bash
# Table IX JMeter benchmark: 5 interactions x 100 sequential requests.
# JMeter runs HERE (load generator), the Pi only serves (see README in docs).
# Usage: ./run-jmeter.sh [base-host] [base-port]
#   ./run-jmeter.sh LayRatePI.local 80
set -u
cd "$(dirname "$0")"

HOST="${1:-LayRatePI.local}"
PORT="${2:-80}"
JMETER="${JMETER_BIN:-$HOME/.local/jmeter/bin/jmeter}"

command -v "$JMETER" >/dev/null || { echo "jmeter not found at $JMETER"; exit 1; }

OUT="results/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT"

for plan in plans/0*.jmx; do
  name=$(basename "$plan" .jmx)
  echo "=== $name ==="
  "$JMETER" -n -t "$plan" -l "$OUT/${name}.jtl" -j "$OUT/${name}.log" \
    -JBASE_HOST="$HOST" -JBASE_PORT="$PORT" 2>&1 | tail -n 2
done

echo "Raw results in $OUT"
