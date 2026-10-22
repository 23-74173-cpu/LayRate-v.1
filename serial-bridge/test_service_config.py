#!/usr/bin/env python3
"""Regression tests for the deployed serial bridge systemd service."""

import unittest
from pathlib import Path


class SerialBridgeServiceConfigTest(unittest.TestCase):
    def test_unit_provisions_writable_spool_and_runtime_directories(self):
        unit = Path(__file__).with_name("layrate-serial.service").read_text()
        self.assertIn("StateDirectory=layrate-serial", unit)
        self.assertIn("RuntimeDirectory=layrate-serial", unit)
        self.assertIn("LAYRATE_DELIVERY_SPOOL=/var/lib/layrate-serial/queue.jsonl", unit)
        self.assertIn("LAYRATE_DEAD_LETTER_DIR=/var/lib/layrate-serial/dead-letter", unit)
        self.assertIn("--heartbeat-state /run/layrate-serial/heartbeat.json", unit)

    def test_unit_uses_supported_bridge_options_and_not_local_url_overrides(self):
        unit = Path(__file__).with_name("layrate-serial.service").read_text()
        exec_start = next(line for line in unit.splitlines() if line.startswith("ExecStart="))
        self.assertIn("--config", exec_start)
        self.assertNotIn("--api-url", exec_start)
        self.assertNotIn("--command-api-url", exec_start)

    def test_deploy_removes_dropins_and_leaves_bridge_stopped(self):
        workflow = Path(__file__).parents[1] / ".github/workflows/deploy.yml"
        text = workflow.read_text()
        self.assertIn('DROPIN_DIR="/etc/systemd/system/layrate-serial.service.d"', text)
        self.assertIn('sudo find "$DROPIN_DIR" -mindepth 1 -print -quit', text)
        self.assertNotIn("sudo systemctl restart layrate-serial", text)
        self.assertNotIn("python3 -m unittest", text)
        self.assertIn('if sudo test -s "$SPOOL"; then', text)
        self.assertIn('if [ -n "$(git status --porcelain)" ]; then', text)
        self.assertIn("sudo systemctl stop layrate-serial", text)
        self.assertIn("php artisan optimize:clear", text)
        self.assertIn('EXPECTED="${GITHUB_SHA}"', text)
        self.assertIn('sudo cmp -s "$TRACKED_UNIT" "$INSTALLED_UNIT"', text)
        self.assertIn("sudo systemctl show layrate-serial -p DropInPaths --value", text)
        self.assertIn("StateDirectory=layrate-serial", Path(__file__).with_name("layrate-serial.service").read_text())

    def test_start_limit_options_are_in_unit_section(self):
        unit = Path(__file__).with_name("layrate-serial.service").read_text()
        unit_section = unit.split("[Service]", 1)[0]
        self.assertIn("StartLimitIntervalSec=0", unit_section)
        self.assertIn("StartLimitBurst=5", unit_section)

    def test_deploy_does_not_start_bridge_and_creates_empty_spool_if_needed(self):
        workflow = Path(__file__).parents[1] / ".github/workflows/deploy.yml"
        text = workflow.read_text()
        self.assertNotIn("sudo systemctl restart layrate-serial", text)
        self.assertIn("sudo install -o layratepi -g www-data -m 0640 /dev/null \"$SPOOL\"", text)

    def test_permanent_rejections_are_dead_lettered_and_do_not_block_replay(self):
        bridge = Path(__file__).with_name("bridge.py").read_text()
        self.assertIn("class PermanentDeliveryError", bridge)
        self.assertIn("self._dead_letter_head(sequence_id, exc.status_code, exc)", bridge)
        self.assertIn("def _permanent_rejection_reason(body):", bridge)
        self.assertIn("continue", bridge[bridge.index("except PermanentDeliveryError as exc:"):bridge.index("except Exception as exc:", bridge.index("except PermanentDeliveryError as exc:"))])


if __name__ == "__main__":
    unittest.main()
