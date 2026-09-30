#!/usr/bin/env bash
# Table IX JMeter throughput leg: 3 scenarios, 5 threads, 60 s each.
# Generator runs HERE; the Pi only serves. Device key via $DEVICE_KEY
# (never committed). Durations/threads/ramp overridable: -JDURATION etc.
# Usage: DEVICE_KEY=<key> ./run-throughput.sh [base-host] [base-port]
set -u
cd "$(dirname "$0")"

[ -n "${DEVICE_KEY:-}" ] || { echo "DEVICE_KEY env var required"; exit 1; }
HOST="${1:-LayRatePI.local}"
PORT="${2:-80}"
JMETER="${JMETER_BIN:-$HOME/.local/jmeter/bin/jmeter}"

command -v "$JMETER" >/dev/null || { echo "jmeter not found at $JMETER"; exit 1; }

OUT="results/$(date +%Y%m%d-%H%M%S)-tp"
mkdir -p "$OUT"

for plan in plans/06-throughput-ingestion plans/07-throughput-retrieval plans/08-throughput-forecast; do
  name=$(basename "$plan")
  echo "=== $name (60 s) ==="
  "$JMETER" -n -t "$plan.jmx" -l "$OUT/${name}.jtl" -j "$OUT/${name}.log" \
    -JDEVICE_KEY="$DEVICE_KEY" 2>&1 | tail -n 2
done

echo "Raw results in $OUT"
echo "Post-run cleanup on Pi: delete DATE(recorded_at)='2020-01-01' rows"
echo "(env/occupancy) + log_date='2020-01-01' production rows (predates the"
echo "deployment, provably synthetic) and verify no stray alerts."
