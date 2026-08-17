#!/usr/bin/env python3
"""Unit tests for the relay/threshold command logic in bridge.py.

Run from serial-bridge/:  python -m unittest test_bridge
Deps: pyserial and requests (the package requirements).
"""

import unittest

from bridge import apply_commands, fetch_relay_command, threshold_command


class RecordingSerial:
    def __init__(self):
        self.writes = []

    def write(self, data):
        self.writes.append(data)

    def reset(self):
        self.writes = []


class ThresholdCommandTest(unittest.TestCase):
    def test_formats_thresh_line(self):
        self.assertEqual(threshold_command(32.5, 27.5), "THRESH:32.5:27.5")

    def test_rounds_to_one_decimal(self):
        self.assertEqual(threshold_command(30.04, 25.06), "THRESH:30.0:25.1")

    def test_missing_thresholds_returns_none(self):
        self.assertIsNone(threshold_command(None, None))
        self.assertIsNone(threshold_command(30.0, None))


class ApplyCommandsTest(unittest.TestCase):
    def setUp(self):
        self.ser = RecordingSerial()
        self.intended = {"mode": "auto", "command": "auto", "on_temp": 30.0, "off_temp": 25.0}

    def test_thresh_written_only_on_change(self):
        last_cmd, last_thresh = apply_commands(
            self.ser, self.intended, None, None, False, None)
        self.assertEqual(self.ser.writes, [b"THRESH:30.0:25.0\n", b"RELAY:AUTO\n"])
        self.assertEqual(last_thresh, "THRESH:30.0:25.0")
        self.assertEqual(last_cmd, ("auto", "auto"))

        # Same polled state again — nothing must be re-sent.
        self.ser.reset()
        last_cmd, last_thresh = apply_commands(
            self.ser, self.intended, last_cmd, None, False, last_thresh)
        self.assertEqual(self.ser.writes, [], "Nothing re-sent when nothing changed.")

        # Thresholds change in the app -> only THRESH is re-sent.
        self.ser.reset()
        changed = dict(self.intended, on_temp=34.5, off_temp=29.5)
        _, last_thresh = apply_commands(
            self.ser, changed, last_cmd, None, False, last_thresh)
        self.assertEqual(self.ser.writes, [b"THRESH:34.5:29.5\n"])
        self.assertEqual(last_thresh, "THRESH:34.5:29.5")

    def test_thresh_resent_after_reconnect(self):
        # last_applied_threshold=None simulates a fresh serial connection
        # (Arduino rebooted, reverted to boot-time defaults) — re-apply.
        _, last_thresh = apply_commands(
            self.ser, self.intended, ("auto", "auto"), None, False, None)
        self.assertEqual(last_thresh, "THRESH:30.0:25.0")

    def test_relay_drift_rewrites_command_but_not_thresh(self):
        manual = {"mode": "manual", "command": "on", "on_temp": 30.0, "off_temp": 25.0}
        last_cmd, last_thresh = apply_commands(self.ser, manual, None, "off", False, None)
        self.assertIn(b"THRESH:30.0:25.0\n", self.ser.writes)
        self.assertIn(b"RELAY:ON\n", self.ser.writes)

        # Drift: reported "off" while manual ON commanded -> RELAY re-sent,
        # THRESH untouched.
        self.ser.reset()
        last_cmd, last_thresh = apply_commands(
            self.ser, manual, last_cmd, "off", False, last_thresh)
        self.assertEqual(self.ser.writes, [b"RELAY:ON\n"])
        self.assertEqual(last_thresh, "THRESH:30.0:25.0")

    def test_safety_block_suppresses_drift_reapply(self):
        manual = {"mode": "manual", "command": "on", "on_temp": 30.0, "off_temp": 25.0}
        last_cmd, last_thresh = apply_commands(self.ser, manual, None, "off", False, None)
        self.ser.reset()

        # Safety flag set -> "OFF (SAFETY)" is the expected response to a manual
        # ON, NOT a drift to be corrected.
        apply_commands(self.ser, manual, last_cmd, "off", True, last_thresh)
        self.assertEqual(self.ser.writes, [])

    def test_missing_thresholds_skips_thresh_write(self):
        no_thresh = {"mode": "auto", "command": "auto", "on_temp": None, "off_temp": None}
        last_cmd, last_thresh = apply_commands(self.ser, no_thresh, None, None, False, None)
        self.assertEqual(self.ser.writes, [b"RELAY:AUTO\n"])
        self.assertIsNone(last_thresh)


class FetchRelayCommandTest(unittest.TestCase):
    class _Resp:
        def __init__(self, status, body):
            self.status_code = status
            self._body = body

        def json(self):
            return self._body

        @property
        def text(self):
            return ""

    class _Session:
        def __init__(self, resp):
            self._resp = resp

        def get(self, url, timeout=None):
            return self._resp

    def test_parses_thresholds_from_payload(self):
        session = self._Session(self._Resp(200, {
            "relay": {"mode": "manual", "command": "on", "on_temp": 32.0, "off_temp": 27.0},
        }))
        out = fetch_relay_command(session, "http://x/api/relay/command")
        self.assertEqual(out["mode"], "manual")
        self.assertEqual(out["command"], "on")
        self.assertEqual(out["on_temp"], 32.0)
        self.assertEqual(out["off_temp"], 27.0)

    def test_absent_thresholds_default_to_none(self):
        session = self._Session(self._Resp(200, {
            "relay": {"mode": "auto", "command": "auto"},
        }))
        out = fetch_relay_command(session, "http://x/api/relay/command")
        self.assertIsNone(out["on_temp"])
        self.assertIsNone(out["off_temp"])

    def test_null_relay_returns_none(self):
        session = self._Session(self._Resp(200, {"relay": None}))
        self.assertIsNone(fetch_relay_command(session, "http://x/api/relay/command"))


if __name__ == "__main__":
    unittest.main()
