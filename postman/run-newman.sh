#!/usr/bin/env bash
# Table IX API benchmark runner: 6 collections x 3 independent runs each.
# Usage (from the postman/ directory):
#   ./run-newman.sh [env-file] [base-url] [device-key]
# Examples:
#   ./run-newman.sh                                   # defaults: layrate-pi env, env baseUrl/deviceKey
#   ./run-newman.sh environments/layrate-pi.json http://127.0.0.1:8000 layrate-pi-dev-key-2026
# Secrets: prefer --env-var deviceKey=... / adminPassword=... over committing
# real credentials into the environment file.
set -u
cd "$(dirname "$0")"

ENV_FILE="${1:-environments/layrate-pi.json}"
BASE_URL="${2:-}"
DEVICE_KEY="${3:-}"

EXTRA_ARGS=()
[ -n "$BASE_URL" ] && EXTRA_ARGS+=(--env-var "baseUrl=$BASE_URL")
[ -n "$DEVICE_KEY" ] && EXTRA_ARGS+=(--env-var "deviceKey=$DEVICE_KEY")

command -v newman >/dev/null || { echo "newman not found; npm install -g newman"; exit 1; }

OUT="results/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT"

for coll in collections/*.json; do
  name=$(basename "$coll" .json)
  for i in 1 2 3; do
    echo "=== $name run $i/3 ==="
    newman run "$coll" -e "$ENV_FILE" "${EXTRA_ARGS[@]}" \
      -r json,cli --reporter-json-export "$OUT/${name}.run${i}.json" \
      || echo "!! $name run $i exited non-zero (see report)"
  done
done

echo "Reports in $OUT (18 JSON files: 6 collections x 3 runs)"
