#!/usr/bin/env python3
"""Table IX F8 — Arduino-to-Raspberry Pi Data Transfer (end-to-end path).

Playwright cannot touch a UART, so these cases drive the REAL bridge path
short of physical copper: canned Arduino serial bytes -> BlockParser.feed()
-> build_payload() (both imported from bridge.py, not reimplemented) ->
HTTP POST with the bridge's own headers -> SensorIngestionController ->
MySQL, asserted by polling the tables inside a 10 s window.

Run from serial-bridge/ with the bridge venv (needs requests + pymysql):
    .venv/bin/python -m unittest test_table_ix_f8 -v

Config via env (dev-DB defaults): LAYRATE_API_URL, LAYRATE_DEVICE_KEY,
DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD.

Benchmark-hygiene note (same contract as the Playwright specs): payloads use
FIXED backdated recorded_at values on 2026-01-15 -- outside the Mar-Jun real
window, the Jul-Sep demo window, and every 24h/7d/30d live readout -- except
that build_payload() stamps UTC now like the real bridge, so the timestamp is
overridden right after the build (visible one-liner, documented here). Reruns
are idempotent (updateOrCreate on identical keys; the 5 s debounce only fires
on fresh timestamps). Terminal cleanup removes the Jan-15 cage-5/slot-61 rows
and today's cage-5 sensor alerts -- see e2e/table-ix/README.md.
"""

import json
import logging
import os
import subprocess
import sys
import time
import unittest

import pymysql
import requests

from bridge import BlockParser, build_payload

API_URL = os.getenv("LAYRATE_API_URL", "http://127.0.0.1:8000/api/sensor-readings")
DEVICE_KEY = os.getenv("LAYRATE_DEVICE_KEY", "layrate-pi-dev-key-2026")
DHT_SERIAL = "DHT22-001"      # -> CAGE-T (cage 5)
IR_SERIAL = "IRBBS-001"       # -> CAGE-T slot 61 (4 active hens)
CAGE_ID = 5
SLOT_ID = 61
DAY = "2026-01-15"
STAMP_22 = f"{DAY}T12:20:00+08:00"
STAMP_23 = f"{DAY}T12:25:00+08:00"
STAMP_24 = f"{DAY}T12:30:00+08:00"

DB = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "user": os.getenv("DB_USERNAME", "root"),
    "password": os.getenv("DB_PASSWORD", "password"),
    "database": os.getenv("DB_DATABASE", "layrate"),
}

log = logging.getLogger("bridge-e2e")

# Exact firmware block layout per bridge.py's docstring, MINUS the optional
# Relay line: pre-fan-control firmware omits it, which keeps the payload to
# the DHT22 + IR legs under test (an unregistered RELAY-001 reading would
# otherwise turn the expected 200 into a 207). Relay parsing is covered by
# test_bridge.py's unit tests.
ARDUINO_BLOCK = """--------------------
Count: {count}
Beam: {beam}
Temp: {temp} C
Humidity: {hum} %
"""


def parse_serial_block(count, beam="UNBROKEN", temp="27.70", hum="66.60"):
    """Feed raw UART-style bytes through the real BlockParser."""
    parser = BlockParser()
    blocks = parser.feed(
        ARDUINO_BLOCK.format(count=count, beam=beam, temp=temp, hum=hum)
    )
    assert len(blocks) == 1, f"expected 1 parsed block, got {len(blocks)}"
    return blocks[0]


def db_scalar(sql, args=()):
    con = pymysql.connect(**DB)
    try:
        with con.cursor() as cur:
            cur.execute(sql, args)
            row = cur.fetchone()
            return row[0] if row else None
    finally:
        con.close()


def wait_for(description, probe, timeout=10.0):
    """Poll `probe` until truthy inside the expected time window."""
    deadline = time.monotonic() + timeout
    while True:
        value = probe()
        if value:
            return value
        if time.monotonic() >= deadline:
            raise AssertionError(f"timed out waiting for: {description}")
        time.sleep(0.5)


class TableIXF8(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.session = requests.Session()
        cls.session.headers.update({
            "X-Device-Key": DEVICE_KEY,
            "Content-Type": "application/json",
            "Accept": "application/json",
        })

    def post_readings(self, readings, recorded_at):
        """Mirror bridge.py run_loop's send-and-log branching exactly."""
        payload = {"readings": readings, "recorded_at": recorded_at}
        resp = self.session.post(API_URL, json=payload, timeout=10)
        if resp.status_code == 200:
            log.info("Sent %d reading(s) to %s (HTTP 200)", len(readings), API_URL)
        elif resp.status_code == 207:
            try:
                body = resp.json()
            except ValueError:
                body = {}
            log.warning("Partial failure sending to %s (HTTP 207): %s",
                        API_URL, body.get("errors", body))
        else:
            log.warning("API HTTP %d: %s", resp.status_code, resp.text[:200])
        return resp

    def build_for_stamp(self, parsed, stamp):
        payload = build_payload(parsed, DHT_SERIAL, IR_SERIAL, "RELAY-001")
        assert payload, "build_payload returned None for a complete block"
        payload["recorded_at"] = stamp  # clock pinned for hygiene (see header)
        return payload

    def test_tc22_valid_block_ingested_end_to_end(self):
        """TC-22 [primary]: canned Arduino bytes land as occupancy +
        production rows within the time window; bridge log shows HTTP 200."""
        parsed = parse_serial_block(count=2)
        self.assertEqual(parsed["count"], 2)
        self.assertAlmostEqual(parsed["temp"], 27.7)
        payload = self.build_for_stamp(parsed, STAMP_22)

        with self.assertLogs("bridge-e2e", level="INFO") as logs:
            resp = self.post_readings(payload["readings"], payload["recorded_at"])
        self.assertEqual(resp.status_code, 200)
        self.assertTrue(any("HTTP 200" in line for line in logs.output))

        body = resp.json()
        kinds = {p["serial_number"]: p["type"] for p in body.get("processed", [])}
        self.assertEqual(kinds.get(DHT_SERIAL), "environment")
        self.assertEqual(kinds.get(IR_SERIAL), "occupancy")

        wait_for(
            "occupancy row",
            lambda: db_scalar(
                "SELECT reported_count FROM sensor_occupancy_readings "
                "WHERE hardware_item_id = (SELECT id FROM hardware_items WHERE serial_number = %s) "
                "AND DATE(recorded_at) = %s AND reported_count = 2", (IR_SERIAL, DAY)),
        )
        eggs = wait_for(
            "production row",
            lambda: db_scalar(
                "SELECT egg_count FROM production_logs "
                "WHERE cage_slot_id = %s AND log_date = %s", (SLOT_ID, DAY)),
        )
        self.assertEqual(int(eggs), 2)
        via = db_scalar(
            "SELECT logged_via FROM production_logs WHERE cage_slot_id = %s AND log_date = %s",
            (SLOT_ID, DAY))
        self.assertEqual(via, "sensor")

    def test_tc23_regressed_count_refused_atomically(self):
        """TC-23 [boundary]: a count below today's sensor log is refused;
        the committed DHT22 leg succeeds (207 partial), the occupancy write
        and sensor-reset alert persist, and the production row keeps its
        higher value."""
        parsed = parse_serial_block(count=1)
        payload = self.build_for_stamp(parsed, STAMP_23)
        # Swap in ONLY the regressed IR leg next to a valid DHT22 leg so the
        # partial path (not the all-rejected path) is exercised.
        readings = [r for r in payload["readings"] if r["serial_number"] != IR_SERIAL]
        readings.append({"serial_number": IR_SERIAL, "count": 1})

        resp = self.post_readings(readings, STAMP_23)
        self.assertEqual(resp.status_code, 207)
        body = resp.json()
        self.assertIn("dropped from 2", json.dumps(body.get("errors", [])))

        kept = wait_for(
            "production row keeps higher value",
            lambda: db_scalar(
                "SELECT egg_count FROM production_logs "
                "WHERE cage_slot_id = %s AND log_date = %s", (SLOT_ID, DAY)),
        )
        self.assertEqual(int(kept), 2)
        occ = db_scalar(
            "SELECT reported_count FROM sensor_occupancy_readings "
            "WHERE hardware_item_id = (SELECT id FROM hardware_items WHERE serial_number = %s) "
            "AND DATE(recorded_at) = %s AND reported_count = 1", (IR_SERIAL, DAY))
        self.assertEqual(int(occ), 1)
        alert = wait_for(
            "sensor-reset alert",
            lambda: db_scalar(
                "SELECT message FROM alerts WHERE cage_id = %s AND alert_type = 'sensor_reset' "
                "AND is_read = 0 ORDER BY triggered_at DESC LIMIT 1", (CAGE_ID,)),
        )
        self.assertIn("dropped from 2", alert)

    def test_tc24_unknown_serial_rejected_and_keyless_bridge_refuses_start(self):
        """TC-24 [error]: unregistered serial -> structured per-reading 422
        (nothing written); bridge without a device key exits 1 pre-serial."""
        before = db_scalar(
            "SELECT COUNT(*) FROM sensor_occupancy_readings "
            "WHERE DATE(recorded_at) = %s", (DAY,))
        resp = self.post_readings(
            [{"serial_number": "NOPE-999", "count": 3}], STAMP_24)
        self.assertEqual(resp.status_code, 422)
        body = resp.json()
        self.assertIn("NOPE-999", json.dumps(body.get("errors", [])))
        after = db_scalar(
            "SELECT COUNT(*) FROM sensor_occupancy_readings "
            "WHERE DATE(recorded_at) = %s", (DAY,))
        self.assertEqual(before, after)

        env = {k: v for k, v in os.environ.items() if k != "LAYrate_DEVICE_KEY"}
        proc = subprocess.run(
            [sys.executable, "bridge.py", "--port", "/dev/nonexistent-tty-for-test",
             "--dht-serial", DHT_SERIAL, "--ir-serial", IR_SERIAL],
            cwd=os.path.dirname(os.path.abspath(__file__)),
            env=env, capture_output=True, text=True, timeout=30,
        )
        self.assertEqual(proc.returncode, 1)
        self.assertIn("Device key required", proc.stderr + proc.stdout)


if __name__ == "__main__":
    logging.basicConfig(
        level=getattr(logging, "INFO"),
        format="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%S",
    )
    unittest.main(verbosity=2)
