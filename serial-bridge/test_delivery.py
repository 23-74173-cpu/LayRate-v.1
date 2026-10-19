#!/usr/bin/env python3
"""Delivery, response-validation, retry, and durable-spool tests."""

import json
import os
import tempfile
import unittest
from pathlib import Path

import requests

from bridge import DeliveryClient, DeliverySpool, api_base_url, resolve_api_base_url


class FakeResponse:
    def __init__(self, status_code, body=None, content_type="application/json", text=None):
        self.status_code = status_code
        self._body = body
        self.headers = {"Content-Type": content_type}
        self.text = text if text is not None else json.dumps(body)

    def json(self):
        if self._body is None:
            raise ValueError("invalid json")
        return self._body


class FakeSession:
    def __init__(self, outcomes):
        self.outcomes = list(outcomes)
        self.calls = []

    def post(self, url, **kwargs):
        self.calls.append((url, kwargs))
        outcome = self.outcomes.pop(0)
        if isinstance(outcome, Exception):
            raise outcome
        return outcome


def payload(count):
    return {
        "readings": [{"serial_number": "IR-TEST", "count": count}],
        "recorded_at": f"2026-10-19T12:00:0{count}+00:00",
        "sequence_id": f"test-{count}",
    }


class ApiBaseUrlTest(unittest.TestCase):
    def test_base_url_default_and_path_normalization(self):
        self.assertEqual(api_base_url(None), "http://127.0.0.1")
        self.assertEqual(api_base_url("http://127.0.0.1/"), "http://127.0.0.1")
        self.assertEqual(api_base_url("http://pi.local/base"), "http://pi.local/base")

    def test_base_url_precedence(self):
        self.assertEqual(resolve_api_base_url("http://cli", {"api_base_url": "http://json"}), "http://cli")
        self.assertEqual(resolve_api_base_url(None, {"api_base_url": "http://json"}), "http://json")

    def test_environment_precedes_config(self):
        old = os.environ.get("LAYRATE_API_BASE_URL")
        os.environ["LAYRATE_API_BASE_URL"] = "http://environment"
        try:
            self.assertEqual(resolve_api_base_url(None, {"api_base_url": "http://json"}), "http://environment")
        finally:
            if old is None:
                os.environ.pop("LAYRATE_API_BASE_URL", None)
            else:
                os.environ["LAYRATE_API_BASE_URL"] = old


class DeliveryClientTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.spool = DeliverySpool(Path(self.temp.name) / "queue.jsonl")
        self.url = "http://127.0.0.1/api/sensor-readings"

    def tearDown(self):
        self.temp.cleanup()

    def client(self, outcomes, attempts=1):
        return DeliveryClient(
            FakeSession(outcomes), self.url, self.spool,
            max_attempts=attempts, base_backoff=0,
        )

    def test_rejects_redirect_without_following_and_spools(self):
        session = FakeSession([FakeResponse(302, {"message": "portal"})])
        client = DeliveryClient(session, self.url, self.spool, max_attempts=1, base_backoff=0)
        self.assertFalse(client.deliver(payload(1)))
        self.assertEqual(session.calls[0][1]["allow_redirects"], False)
        self.assertEqual(self.spool.count(), 1)

    def test_connection_refused_and_timeout_are_queued(self):
        for error in (requests.ConnectionError("refused"), requests.Timeout("timed out")):
            with self.subTest(error=type(error).__name__):
                spool = DeliverySpool(Path(self.temp.name) / f"{type(error).__name__}.jsonl")
                client = DeliveryClient(FakeSession([error]), self.url, spool,
                                        max_attempts=1, base_backoff=0)
                self.assertFalse(client.deliver(payload(2)))
                self.assertEqual(spool.count(), 1)

    def test_rejects_malformed_body_and_unexpected_status(self):
        cases = [
            FakeResponse(200, None, "text/html", "portal html"),
            FakeResponse(200, {"unexpected": True}),
            FakeResponse(500, {"message": "server error"}),
        ]
        for index, response in enumerate(cases):
            with self.subTest(status=response.status_code, body=response.text):
                spool = DeliverySpool(Path(self.temp.name) / f"case-{index}.jsonl")
                client = DeliveryClient(FakeSession([response]), self.url, spool,
                                        max_attempts=1, base_backoff=0)
                self.assertFalse(client.deliver(payload(index + 1)))
                self.assertEqual(spool.count(), 1)

    def test_retries_then_accepts_expected_json_ack(self):
        response = FakeResponse(200, {
            "message": "Readings accepted.", "accepted": 1,
            "processed": [{"serial_number": "IR-TEST", "type": "occupancy"}],
        })
        session = FakeSession([requests.Timeout("once"), response])
        client = DeliveryClient(session, self.url, self.spool, max_attempts=2, base_backoff=0)
        self.assertTrue(client.deliver(payload(1)))
        self.assertEqual(len(session.calls), 2)
        self.assertEqual(self.spool.count(), 0)

    def test_health_probe_ack_is_valid_without_occupancy_accepted_count(self):
        probe = {"readings": [{"serial_number": "IR-TEST", "health_probe": True}],
                 "health_probe": True, "probe_id": "probe-123", "recorded_at": "now"}
        response = FakeResponse(200, {
            "message": "Readings accepted.", "accepted": 1,
            "processed": [{"serial_number": "IR-TEST", "type": "health_probe"}],
        })
        client = DeliveryClient(FakeSession([response]), self.url, self.spool,
                                max_attempts=1, base_backoff=0)
        self.assertTrue(client.deliver(probe))

    def test_no_accepted_readings_is_not_an_acknowledgement(self):
        response = FakeResponse(200, {"message": "No readings were accepted.", "accepted": 0,
                                      "processed": [], "errors": ["rejected"]})
        client = DeliveryClient(FakeSession([response]), self.url, self.spool,
                                max_attempts=1, base_backoff=0)
        self.assertFalse(client.deliver(payload(1)))

    def test_spool_replays_in_order_and_ack_removes_one_at_a_time(self):
        self.spool.append(payload(1))
        self.spool.append(payload(2))
        self.spool.append(payload(3))
        responses = [FakeResponse(200, {
            "message": "Readings accepted.", "accepted": 1,
            "processed": [{"serial_number": "IR-TEST", "type": "occupancy"}],
        }) for _ in range(3)]
        session = FakeSession(responses)
        client = DeliveryClient(session, self.url, self.spool, max_attempts=1, base_backoff=0)

        self.assertEqual(client.replay_spool(), 3)
        self.assertEqual([call[1]["json"]["readings"][0]["count"] for call in session.calls],
                         [1, 2, 3])
        self.assertEqual(self.spool.count(), 0)

    def test_delivery_sequence_id_deduplicates_spool_enqueue(self):
        self.spool.append(payload(1))
        self.spool.append(payload(1))
        self.assertEqual(self.spool.count(), 1)

    def test_same_live_sequence_id_is_not_posted_twice_after_queued_failure(self):
        session = FakeSession([requests.Timeout("unavailable")])
        client = DeliveryClient(session, self.url, self.spool, max_attempts=1, base_backoff=0)
        queued = payload(1)
        self.assertFalse(client.deliver(queued))
        self.assertFalse(client.deliver(dict(queued)))
        self.assertEqual(len(session.calls), 1)
        self.assertEqual(self.spool.count(), 1)

    def test_acknowledged_response_must_include_all_sensor_serials(self):
        response = FakeResponse(200, {
            "message": "Readings accepted.", "accepted": 1,
            "processed": [{"serial_number": "OTHER-SENSOR", "type": "occupancy"}],
        })
        client = DeliveryClient(FakeSession([response]), self.url, self.spool,
                                max_attempts=1, base_backoff=0)
        self.assertFalse(client.deliver(payload(1)))
        self.assertEqual(self.spool.count(), 1)

    def test_failed_head_blocks_later_payload_replay(self):
        self.spool.append(payload(1))
        self.spool.append(payload(2))
        session = FakeSession([FakeResponse(302, {"message": "portal"})])
        client = DeliveryClient(session, self.url, self.spool, max_attempts=1, base_backoff=0)
        self.assertEqual(client.replay_spool(), 0)
        self.assertEqual(self.spool.count(), 2)
        self.assertEqual(len(session.calls), 1)


if __name__ == "__main__":
    unittest.main()
