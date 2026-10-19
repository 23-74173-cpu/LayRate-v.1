#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${LAYRATE_API_BASE_URL:-http://127.0.0.1}"
HEARTBEAT_URL="${LAYRATE_BRIDGE_HEARTBEAT_URL:-http://127.0.0.1:8765/health}"
for url in "${BASE_URL%/}/api/sensor-readings" "$HEARTBEAT_URL"; do
  probe_payload=""
  request_headers=(-H 'Accept: application/json')
  if [[ "$url" == *"/api/sensor-readings"* ]]; then
    probe_serial="${LAYRATE_HEALTH_PROBE_SERIAL:-}"
    if [[ -z "$probe_serial" ]]; then
      echo "ERROR: LAYRATE_HEALTH_PROBE_SERIAL must identify a dedicated active health-probe sensor." >&2
      exit 2
    fi
    KEY="${LAYrate_DEVICE_KEY:-}"
    if [[ -z "$KEY" ]]; then
      echo "ERROR: LAYrate_DEVICE_KEY is required; refusing to use an unspecified credential." >&2
      exit 2
    fi
    request_headers+=(-H "X-Device-Key: $KEY" -H 'Content-Type: application/json' -H 'X-LayRate-Health-Probe: 1')
    probe_payload=$(python3 -c 'import json,sys,datetime; print(json.dumps({"health_probe":True,"probe_id":"health-"+datetime.datetime.now(datetime.timezone.utc).isoformat(),"readings":[{"serial_number":sys.argv[1],"health_probe":True}],"recorded_at":datetime.datetime.now(datetime.timezone.utc).isoformat()}))' "$probe_serial")
  fi
  curl_args=(--silent --show-error --max-time 5 --dump-header /tmp/layrate-health-headers \
    --output /tmp/layrate-health-body --write-out '%{http_code}')
  curl_args+=("${request_headers[@]}")
  if [[ -n "${probe_payload:-}" ]]; then
    curl_args+=(--data "$probe_payload")
  fi
  response=$(curl "${curl_args[@]}" "$url") || {
      echo "ERROR: request failed url=$url" >&2
      exit 1
    }
  if [[ "$response" != "200" ]] || ! grep -qi '^Content-Type: application/json' /tmp/layrate-health-headers; then
    echo "ERROR: unexpected app response url=$url HTTP=$response" >&2
    head -c 240 /tmp/layrate-health-body >&2
    exit 1
  fi
  if [[ "$url" == *"/api/sensor-readings"* ]] && grep -qi '^Location:' /tmp/layrate-health-headers; then
    echo "ERROR: app health probe unexpectedly returned a Location header url=$url" >&2
    exit 1
  fi
  if [[ "$url" == *"/api/sensor-readings"* ]]; then
    python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); assert d.get("accepted",0)==1 and d.get("processed",[{}])[0].get("type")=="health_probe", "missing health-probe acknowledgement"' /tmp/layrate-health-body
  else
    cp /tmp/layrate-health-body /tmp/layrate-bridge-state.json
  fi
done

python3 - <<'PY'
import json
from pathlib import Path

state = json.loads(Path('/tmp/layrate-bridge-state.json').read_text())
required = ('serial_connected', 'serial_activity_at', 'delivery_at', 'sent', 'failed', 'queued')
missing = [key for key in required if key not in state]
if missing:
    raise SystemExit(f"ERROR: heartbeat response missing fields: {missing}")
if not state['serial_connected']:
    raise SystemExit('ERROR: bridge reports serial disconnected')
if not state.get('serial_activity_fresh'):
    raise SystemExit('ERROR: no fresh serial activity (90-second threshold)')
if state.get('delivery_error'):
    raise SystemExit(f"ERROR: latest delivery error: {state['delivery_error']}")
if state.get('queued', 0):
    raise SystemExit(f"ERROR: delivery spool contains {state['queued']} pending reading(s)")
print(json.dumps(state, indent=2))
PY
