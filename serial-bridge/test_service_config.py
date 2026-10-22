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
        self.assertIn("--heartbeat-state /run/layrate-serial/heartbeat.json", unit)

    def test_unit_uses_supported_bridge_options_and_not_local_url_overrides(self):
        unit = Path(__file__).with_name("layrate-serial.service").read_text()
        exec_start = next(line for line in unit.splitlines() if line.startswith("ExecStart="))
        self.assertIn("--config", exec_start)
        self.assertNotIn("--api-url", exec_start)
        self.assertNotIn("--command-api-url", exec_start)

    def test_deploy_backs_up_and_reconciles_serial_dropins_on_bridge_deploy(self):
        workflow = Path(__file__).parents[1] / ".github/workflows/deploy.yml"
        text = workflow.read_text()
        self.assertIn('DROPIN="/etc/systemd/system/layrate-serial.service.d/override.conf"', text)
        self.assertIn("Environment=LAYRATE_API_BASE_URL=http://127.0.0.1", text)
        self.assertIn('sudo cp "$DROPIN" "$BACKUP"', text)
        self.assertIn("sudo systemctl restart layrate-serial", text)
        self.assertIn("StateDirectory=layrate-serial", Path(__file__).with_name("layrate-serial.service").read_text())


if __name__ == "__main__":
    unittest.main()
