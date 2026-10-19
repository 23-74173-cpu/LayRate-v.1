#!/usr/bin/env python3
"""Non-counting bridge -> Nginx -> app -> DB smoke test.

Requires an explicitly provisioned active health-probe sensor. The marked probe
is acknowledged by the app and deliberately does not change production data.
"""

import argparse
import os
import sys
import time
from datetime import datetime, timezone

import pymysql
import requests


def post_probe(base_url, key, serial, host_override=None, sequence_id=None):
    headers = {"X-Device-Key": key, "Accept": "application/json", "Content-Type": "application/json",
               "X-LayRate-Health-Probe": "1"}
    if host_override:
        headers["Host"] = host_override
    session = requests.Session()
    response = session.post(
        f"{base_url.rstrip('/')}/api/sensor-readings",
        headers=headers,
        json={
            "health_probe": True,
            "probe_id": sequence_id or f"probe-{datetime.now(timezone.utc).timestamp()}",
            "recorded_at": datetime.now(timezone.utc).isoformat(),
            "readings": [{"serial_number": serial, "health_probe": True}],
        },
        timeout=10,
        allow_redirects=False,
    )
    if response.status_code != 200:
        if response.status_code == 302:
            raise RuntimeError(f"{base_url}: got 302 redirect to {response.headers.get('Location')}")
        raise RuntimeError(f"{base_url}: expected 200 app JSON, got {response.status_code}; {response.text[:240]!r}")
    if "application/json" not in response.headers.get("Content-Type", "").lower():
        raise RuntimeError(f"{base_url}: non-JSON response {response.headers.get('Content-Type')!r}")
    body = response.json()
    if body.get("message") != "Readings accepted." or not body.get("processed"):
        raise RuntimeError(f"{base_url}: invalid application acknowledgment {body!r}")
    if body["processed"][0].get("type") != "health_probe":
        raise RuntimeError(f"{base_url}: app did not acknowledge a health probe {body!r}")
    return body


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default=os.getenv("LAYRATE_API_BASE_URL", "http://127.0.0.1"))
    parser.add_argument("--device-key", default=os.getenv("LAYrate_DEVICE_KEY"))
    parser.add_argument("--serial", default=os.getenv("LAYRATE_HEALTH_PROBE_SERIAL", os.getenv("LAYRATE_SMOKE_SERIAL")))
    parser.add_argument("--ipv6-url", default=os.getenv("LAYRATE_SMOKE_IPV6_URL"))
    parser.add_argument("--expect-ipv6", choices=("success", "redirect", "refused"), default="refused")
    parser.add_argument("--db-host", default=os.getenv("DB_HOST", "127.0.0.1"))
    parser.add_argument("--db-port", type=int, default=int(os.getenv("DB_PORT", "3306")))
    parser.add_argument("--db-user", default=os.getenv("DB_USERNAME"))
    parser.add_argument("--db-password", default=os.getenv("DB_PASSWORD"))
    parser.add_argument("--database", default=os.getenv("DB_DATABASE", "layrate"))
    args = parser.parse_args()

    if not args.device_key or not args.serial or not args.db_user:
        parser.error("--device-key, --serial, and --db-user are required; configure an active health-probe device")

    connection = pymysql.connect(host=args.db_host, port=args.db_port, user=args.db_user,
                                 password=args.db_password or "", database=args.database)
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT id, device_id, cage_slot_id, last_valid_reading_at FROM hardware_items WHERE serial_number=%s AND status='active'", (args.serial,))
            item = cursor.fetchone()
            if not item:
                raise RuntimeError(f"health-probe serial {args.serial} must identify an active hardware item")
            hardware_id, _device_id, slot_id, prior_seen = item

        probe_sequence = f"smoke-probe-{datetime.now(timezone.utc).timestamp()}"
        body = post_probe(args.base_url, args.device_key, args.serial, sequence_id=probe_sequence)
        print(f"IPv4/API {args.base_url}: HTTP 200, accepted={body.get('accepted')}")

        if args.ipv6_url:
            try:
                body6 = post_probe(args.ipv6_url, args.device_key, args.serial, sequence_id=probe_sequence)
                if args.expect_ipv6 != "success":
                    raise RuntimeError(f"IPv6 unexpectedly succeeded; expected {args.expect_ipv6}")
                print(f"IPv6/API {args.ipv6_url}: HTTP 200 (intentional)")
            except requests.ConnectionError:
                if args.expect_ipv6 != "refused":
                    raise
                print(f"IPv6/API {args.ipv6_url}: connection refused (intentional)")
            except RuntimeError as exc:
                if args.expect_ipv6 != "redirect" or "got 302" not in str(exc):
                    raise
                print(f"IPv6/API {args.ipv6_url}: captive redirect detected (expected by configuration)")

        deadline = time.monotonic() + 10
        while time.monotonic() < deadline:
            with connection.cursor() as cursor:
                cursor.execute("SELECT COUNT(*) FROM hardware_items WHERE id=%s", (hardware_id,))
                stored = cursor.fetchone()[0]
                cursor.execute("SELECT last_valid_reading_at FROM hardware_items WHERE id=%s", (hardware_id,))
                current_seen = cursor.fetchone()[0]
            if stored and current_seen == prior_seen:
                print(f"DB verified: device id={hardware_id} exists; health probe acknowledged; production health timestamp unchanged={current_seen}")
                return 0
            time.sleep(0.25)
        raise RuntimeError("API accepted request but health-probe verification failed")
    finally:
        connection.close()


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:
        print(f"SMOKE TEST FAILED: {exc}", file=sys.stderr)
        sys.exit(1)
