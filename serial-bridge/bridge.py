#!/usr/bin/env python3
"""
LayRate Serial Bridge

Reads Arduino Uno serial output, parses formatted blocks, and forwards
sensor readings to the LayRate PHP API via HTTP POST.

Expected serial format from the Arduino firmware:

    \f
    --------------------
    Count: <int>
    Beam: BROKEN|UNBROKEN
    Temp: <float> C | DHT22 Error - Check wiring | --.-- C
    Humidity: <float> % | --.-- %
    Relay: ON|OFF|OFF (SAFETY)   (optional line, absent on older firmware)

Runs as a systemd service on the Raspberry Pi. In addition to forwarding
readings, it polls GET /api/relay/command and writes the returned commands to
the Arduino over serial (bidirectional fan control — see RelayCommandController
for the intended-state semantics):

  - RELAY:ON|OFF|AUTO  the manual/auto control mode
  - THRESH:<on>:<off>  the app-configured AUTO hysteresis thresholds
                       (sent only when they change or after a reconnect)

Both are polled intended-state values written only on change/drift, never
spammed every poll cycle.
"""

import argparse
import json
import logging
import os
import re
import socket
import sys
import tempfile
import threading
import time
import uuid
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit

import requests
import serial
from serial.tools import list_ports

DEFAULT_BAUD = 9600
DEFAULT_API_BASE_URL = "http://127.0.0.1"
POLL_INTERVAL = 0.05
COMMAND_POLL_INTERVAL = 2.0
RECONNECT_DELAY = 5
ENV_KEY_PREFIX = "LAYrate_"
DEFAULT_CONFIG_PATH = os.path.join(os.path.dirname(__file__), "sensors.json")
DEFAULT_SPOOL_PATH = "/var/lib/layrate-serial/queue.jsonl"
MAX_SPOOL_BYTES = 50 * 1024 * 1024
MAX_QUEUE_ITEMS = 20000
DELIVERY_MAX_ATTEMPTS = 3
DELIVERY_BACKOFF_SECONDS = 0.5
HEARTBEAT_INTERVAL = 60
SERIAL_STALE_SECONDS = 90


def api_base_url(configured=None):
    """Normalize the sole API base setting to an origin/base path."""
    value = (configured or os.getenv("LAYRATE_API_BASE_URL") or DEFAULT_API_BASE_URL).strip()
    parsed = urlsplit(value)
    if parsed.scheme not in ("http", "https") or not parsed.netloc:
        raise ValueError("LAYRATE_API_BASE_URL must be an absolute http(s) URL")
    if parsed.query or parsed.fragment:
        raise ValueError("LAYRATE_API_BASE_URL cannot contain a query or fragment")
    return urlunsplit((parsed.scheme, parsed.netloc, parsed.path.rstrip("/"), "", ""))


def resolve_api_base_url(cli_value, config):
    """Explicit precedence: CLI, env, config, then safe IPv4 default."""
    return api_base_url(cli_value or os.getenv("LAYRATE_API_BASE_URL") or config.get("api_base_url"))


def api_endpoint(base_url, path):
    return f"{base_url.rstrip('/')}/{path.lstrip('/')}"


def delivery_sequence_id(payload):
    """Stable ID for a logical sensor batch across retries and spool replay."""
    if payload.get("sequence_id"):
        return str(payload["sequence_id"])
    payload["sequence_id"] = str(uuid.uuid4())
    return payload["sequence_id"]


class DeliverySpool:
    """Bounded JSONL spool; only the head is removed after a valid API ACK."""

    def __init__(self, path, max_bytes=MAX_SPOOL_BYTES, max_items=MAX_QUEUE_ITEMS):
        self.path = Path(path)
        self.max_bytes = max_bytes
        self.max_items = max_items
        self._lock = threading.Lock()

    def _read_lines(self):
        if not self.path.exists():
            return []
        with self.path.open("r", encoding="utf-8") as handle:
            return [line for line in handle if line.strip()]

    def read_payloads(self):
        with self._lock:
            return [json.loads(line) for line in self._read_lines()]

    def read_payloads(self):
        with self._lock:
            return [json.loads(line) for line in self._read_lines()]

    def count(self):
        with self._lock:
            try:
                return len(self._read_lines())
            except OSError:
                return 0

    def append(self, payload):
        encoded = json.dumps(payload, separators=(",", ":")) + "\n"
        self.path.parent.mkdir(parents=True, exist_ok=True)
        with self._lock:
            lines = self._read_lines()
            if any(json.loads(line).get("sequence_id") == payload.get("sequence_id") for line in lines):
                return
            if len(lines) >= self.max_items or sum(map(len, lines)) + len(encoded) > self.max_bytes:
                raise OSError("delivery spool is full; refusing to discard an unacknowledged reading")
            with self.path.open("a", encoding="utf-8") as handle:
                handle.write(encoded)
                handle.flush()
                os.fsync(handle.fileno())

    def append_many(self, payloads):
        for payload in payloads:
            self.append(payload)

    def peek(self):
        with self._lock:
            lines = self._read_lines()
            if not lines:
                return None
            return json.loads(lines[0])

    def acknowledge_head(self, sequence_id):
        with self._lock:
            lines = self._read_lines()
            if not lines:
                return False
            payload = json.loads(lines[0])
            if delivery_sequence_id(payload) != sequence_id:
                return False
            remaining = lines[1:]
            self.path.parent.mkdir(parents=True, exist_ok=True)
            fd, tmp_name = tempfile.mkstemp(prefix=f"{self.path.name}.", dir=self.path.parent)
            try:
                with os.fdopen(fd, "w", encoding="utf-8") as handle:
                    handle.writelines(remaining)
                    handle.flush()
                    os.fsync(handle.fileno())
                os.replace(tmp_name, self.path)
            finally:
                if os.path.exists(tmp_name):
                    os.unlink(tmp_name)
            return True


class RetryableDeliveryError(Exception):
    pass


class PermanentDeliveryError(Exception):
    def __init__(self, status_code, message):
        super().__init__(message)
        self.status_code = int(status_code)


class DeliveryClient:
    """Strict HTTP delivery with bounded retry and ordered durable replay."""

    def __init__(self, session, url, spool, max_attempts=DELIVERY_MAX_ATTEMPTS,
                 base_backoff=DELIVERY_BACKOFF_SECONDS, dead_letter_dir=None):
        self.session = session
        self.url = url
        self.spool = spool
        self.dead_letter_dir = Path(dead_letter_dir) if dead_letter_dir else self.spool.path.parent / "dead-letter"
        self.max_attempts = max(1, int(max_attempts))
        self.base_backoff = max(0.0, float(base_backoff))
        self.sent = 0
        self.failed = 0
        self._counter_lock = threading.Lock()
        self._lock = threading.RLock()
        self._attempted_ids = set()

    def _increment_sent(self):
        with self._counter_lock:
            self.sent += 1

    def _increment_failed(self):
        with self._counter_lock:
            self.failed += 1

    def stats(self):
        with self._counter_lock:
            return self.sent, self.failed

    def _mark_failed(self):
        with self._counter_lock:
            self.failed += 1

    @staticmethod
    def _expected_processed(payload, body):
        if payload.get("health_probe") is True and any(
            item.get("type") == "health_probe" for item in body.get("processed", [])
        ):
            return True
        expected = {item.get("serial_number") for item in payload.get("readings", [])}
        accepted = {item.get("serial_number") for item in body.get("processed", [])}
        return expected.issubset(accepted)

    @staticmethod
    def _post_body(payload):
        data = dict(payload)
        data.pop("sequence_id", None)
        return data

    @staticmethod
    def _is_baseline(payload):
        egg_readings = [reading for reading in payload.get("readings", []) if "count" in reading]
        return bool(egg_readings) and all(
            reading.get("new_trigger_count", 0) == 0
            and reading.get("trigger_count", 0) == 0
            for reading in egg_readings
        )

    @staticmethod
    def _permanent_rejection_reason(body):
        for item in body.get("processed", []):
            if not isinstance(item, dict) or item.get("flagged") is not True:
                continue
            if item.get("baseline") is True:
                continue
            if (item.get("type") == "occupancy" and item.get("trigger_events", 0) == 0
                    and item.get("duplicate") is not True and item.get("accepted") is not True):
                return item.get("reason") or "sensor event was rejected by the application"
        return None

    @staticmethod
    def _is_accepted_baseline(payload, body):
        baseline_serials = {
            item.get("serial_number")
            for item in payload.get("readings", [])
            if "count" in item and item.get("new_trigger_count", 0) == 0
        }
        accepted_baseline_serials = {
            item.get("serial_number")
            for item in body.get("processed", [])
            if item.get("baseline") is True
        }
        expected = {item.get("serial_number") for item in payload.get("readings", [])}
        accepted = {item.get("serial_number") for item in body.get("processed", [])}
        return bool(baseline_serials) and baseline_serials.issubset(accepted_baseline_serials) and expected.issubset(accepted | baseline_serials)

    def _post(self, payload):
        response = self.session.post(
            self.url, json=self._post_body(payload), timeout=10, allow_redirects=False,
        )
        sequence_id = delivery_sequence_id(payload)
        content_type = response.headers.get("Content-Type", "").lower()
        if 400 <= response.status_code < 500:
            raise PermanentDeliveryError(
                response.status_code,
                f"HTTP {response.status_code}; response={response.text[:240]!r}",
            )
        if response.status_code >= 500:
            raise RetryableDeliveryError(
                f"HTTP {response.status_code}; response={response.text[:240]!r}"
            )
        if response.status_code != 200:
            raise PermanentDeliveryError(
                response.status_code,
                f"Unexpected HTTP {response.status_code}; response={response.text[:240]!r}",
            )
        if "application/json" not in content_type:
            raise RetryableDeliveryError(
                f"HTTP 200 with non-JSON Content-Type {content_type!r}; response={response.text[:240]!r}"
            )
        try:
            body = response.json()
        except (ValueError, json.JSONDecodeError) as exc:
            raise RetryableDeliveryError(
                f"HTTP 200 with malformed JSON; response={response.text[:240]!r}"
            ) from exc
        if not isinstance(body, dict) or body.get("message") != "Readings accepted." or not isinstance(body.get("processed", []), list):
            raise RetryableDeliveryError(
                f"HTTP 200 with unexpected acknowledgement; response={response.text[:240]!r}"
            )
        rejection_reason = self._permanent_rejection_reason(body)
        if rejection_reason is not None:
            raise PermanentDeliveryError(
                422,
                f"Application rejected sensor event ({rejection_reason}); response={response.text[:240]!r}",
            )
        if body.get("errors") and not self._is_accepted_baseline(payload, body):
            raise RetryableDeliveryError(
                f"HTTP 200 partial/unaccepted payload; response={response.text[:240]!r}"
            )
        if not self._expected_processed(payload, body):
            if not self._is_accepted_baseline(payload, body):
                raise RetryableDeliveryError(
                    f"HTTP 200 partial/unaccepted payload; response={response.text[:240]!r}"
                )
        return sequence_id

    def _deliver(self, payload):
        sequence_id = delivery_sequence_id(payload)
        for attempt in range(1, self.max_attempts + 1):
            try:
                self._post(payload)
                return sequence_id
            except PermanentDeliveryError:
                raise
            except (requests.RequestException, RetryableDeliveryError, OSError, ValueError) as exc:
                response = getattr(exc, "response", None)
                snippet = getattr(response, "text", "")
                status = getattr(response, "status_code", "n/a")
                if status == "n/a" and isinstance(exc, RetryableDeliveryError):
                    status_match = re.search(r"HTTP (\d{3})", str(exc))
                    if status_match:
                        status = status_match.group(1)
                log.error("Delivery failed url=%s sequence_id=%s attempt=%d/%d status=%s error=%s response=%r",
                          self.url, sequence_id, attempt, self.max_attempts,
                          status, str(exc), snippet[:240] if snippet else str(exc)[:240])
                if attempt < self.max_attempts:
                    time.sleep(self.base_backoff * (2 ** (attempt - 1)))
        return None

    def _dead_letter_head(self, sequence_id, status_code, message):
        payload = self.spool.peek()
        if payload is None or delivery_sequence_id(payload) != sequence_id:
            return False
        self.dead_letter_dir.mkdir(parents=True, exist_ok=True)
        record = dict(payload)
        record["dead_letter"] = {
            "status_code": int(status_code),
            "reason": str(message)[:500],
            "failed_at": datetime.now(timezone.utc).isoformat(),
        }
        target = self.dead_letter_dir / f"{sequence_id}.json"
        temp_file = None
        fd, tmp_name = tempfile.mkstemp(prefix=f"{sequence_id}.", dir=self.dead_letter_dir)
        try:
            temp_file = os.fdopen(fd, "w", encoding="utf-8")
            with temp_file as handle:
                json.dump(record, handle, separators=(",", ":"))
                handle.write("\n")
                handle.flush()
                os.fsync(handle.fileno())
            while True:
                if target.exists():
                    target = self.dead_letter_dir / f"{sequence_id}.{uuid.uuid4().hex}.json"
                    continue
                try:
                    os.link(tmp_name, target)
                    break
                except FileExistsError:
                    target = self.dead_letter_dir / f"{sequence_id}.{uuid.uuid4().hex}.json"
        finally:
            if temp_file is None:
                os.close(fd)
            if os.path.exists(tmp_name):
                os.unlink(tmp_name)
        if not self.spool.acknowledge_head(sequence_id):
            raise RuntimeError(f"spool head changed while dead-lettering {sequence_id}")
        log.error("Moved permanently rejected payload sequence_id=%s status=%d dead_letter=%s",
                  sequence_id, status_code, target)
        return True

    def deliver(self, payload):
        with self._lock:
            return self._deliver_and_spool(payload)

    def _deliver_and_spool(self, payload):
        sequence_id = delivery_sequence_id(payload)
        existing = self.spool.read_payloads()
        if any(item.get("sequence_id") == sequence_id for item in existing):
            return False
        if sequence_id in self._attempted_ids:
            return False
        self._attempted_ids.add(sequence_id)
        try:
            self.spool.append(payload)
        except OSError as exc:
            self._increment_failed()
            log.critical("Cannot queue reading url=%s sequence_id=%s error=%s", self.url, sequence_id, exc)
            return False
        was_head = self.spool.peek().get("sequence_id") == sequence_id
        replayed = self.replay_spool()
        return was_head and replayed > 0

    def deliver_batch(self, payloads):
        with self._lock:
            return self._deliver_batch(payloads)

    def _deliver_batch(self, payloads):
        if not payloads:
            return False
        sequence_ids = [delivery_sequence_id(payload) for payload in payloads]
        if any(sequence_id in self._attempted_ids for sequence_id in sequence_ids):
            return False
        self._attempted_ids.update(sequence_ids)
        try:
            self.spool.append_many(payloads)
        except OSError as exc:
            self._increment_failed()
            log.critical("Cannot queue sensor batch url=%s sequence_ids=%s error=%s",
                         self.url, sequence_ids, exc)
            return False
        head = self.spool.peek()
        replayed = self.replay_spool()
        return bool(head and head.get("sequence_id") in sequence_ids and replayed >= len(sequence_ids))

    def replay_spool(self):
        with self._lock:
            return self._replay_spool()

    def _replay_spool(self):
        replayed = 0
        while True:
            payload = self.spool.peek()
            if payload is None:
                break
            sequence_id = delivery_sequence_id(payload)
            try:
                acknowledged = self._deliver(payload)
            except PermanentDeliveryError as exc:
                self._mark_failed()
                try:
                    if not self._dead_letter_head(sequence_id, exc.status_code, exc):
                        break
                except OSError:
                    log.exception("Could not dead-letter permanent rejection sequence_id=%s; preserving spool head",
                                  sequence_id)
                    break
                continue
            except Exception as exc:  # keep the durable head; never skip order
                log.exception("Spool replay failed url=%s sequence_id=%s: %s", self.url, sequence_id, exc)
                break
            if acknowledged is None:
                self._mark_failed()
                break
            if self.spool.acknowledge_head(sequence_id):
                replayed += 1
                self._increment_sent()
                with self._counter_lock:
                    self.failed = max(0, self.failed - 1)
            else:
                log.error("Spool head changed before acknowledgement sequence_id=%s", sequence_id)
                break
        return replayed


class BridgeHeartbeat:
    """Expose distinct serial activity and API delivery status to local probes."""

    def __init__(self, path):
        self.path = Path(path)
        self.lock = threading.Lock()
        self.serial_activity_at = None
        self.delivery_at = None
        self.serial_connected = False
        self.delivery_error = None
        self.sent = 0
        self.failed = 0
        self.queued = 0
        self.state_lock = threading.Lock()

    def update_serial(self, connected, activity=False):
        with self.lock:
            self.serial_connected = bool(connected)
            if activity:
                self.serial_activity_at = datetime.now(timezone.utc).isoformat()
            self.write()

    def update_delivery(self, success, error=None):
        with self.lock:
            if success:
                self.delivery_at = datetime.now(timezone.utc).isoformat()
                self.delivery_error = None
            else:
                self.delivery_error = str(error)[:240] if error else "delivery_failed"
            self.write()

    def update_counts(self, sent, failed, queued):
        with self.lock:
            self.sent, self.failed, self.queued = sent, failed, queued
            self.write()

    def write(self):
        with self.state_lock:
            self.path.parent.mkdir(parents=True, exist_ok=True)
            state = {
                "updated_at": datetime.now(timezone.utc).isoformat(),
                "serial_connected": self.serial_connected,
                "serial_activity_at": self.serial_activity_at,
                "delivery_at": self.delivery_at,
                "delivery_error": self.delivery_error,
                "sent": self.sent,
                "failed": self.failed,
                "queued": self.queued,
            }
            tmp = self.path.with_suffix(self.path.suffix + ".tmp")
            tmp.write_text(json.dumps(state), encoding="utf-8")
            os.replace(tmp, self.path)


def write_heartbeat_http_state(path, serial_connected, serial_activity_at, delivery_at,
                               delivery_error, sent, failed, queued):
    """Small local HTTP heartbeat endpoint served by the bridge process."""
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps({
        "updated_at": datetime.now(timezone.utc).isoformat(),
        "serial_connected": serial_connected,
        "serial_activity_at": serial_activity_at,
        "delivery_at": delivery_at,
        "delivery_error": delivery_error,
        "sent": sent,
        "failed": failed,
        "queued": queued,
    }), encoding="utf-8")
    os.replace(tmp, path)


class HeartbeatServer(ThreadingHTTPServer):
    daemon_threads = True
    allow_reuse_address = True
    address_family = socket.AF_INET


def start_heartbeat_server(host, port, state_path):
    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            if self.path != "/health" or self.client_address[0] != "127.0.0.1":
                self.send_error(404)
                return
            try:
                body = state_path.read_bytes()
                state = json.loads(body)
                age = None
                activity = state.get("serial_activity_at")
                if activity:
                    age = (datetime.now(timezone.utc) - datetime.fromisoformat(activity)).total_seconds()
                state["serial_activity_fresh"] = age is not None and age <= SERIAL_STALE_SECONDS
                payload = json.dumps(state).encode()
                status = 200 if state.get("serial_connected") and state.get("delivery_at") and state.get("serial_activity_fresh") else 503
            except (OSError, ValueError, json.JSONDecodeError):
                payload = b'{"status":"starting"}'
                status = 503
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        def log_message(self, _format, *_args):
            return

        def do_OPTIONS(self):
            self.send_error(405)

    server = HeartbeatServer((host, int(port)), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    return server


def find_arduino_port():
    ports = list_ports.comports()
    for p in ports:
        desc = (p.description or "") + (p.manufacturer or "")
        if "Arduino" in desc or "USB" in desc:
            return p.device
    return None


def resolve_port(port_arg, auto_flag):
    if port_arg:
        return port_arg
    if auto_flag:
        found = find_arduino_port()
        if found:
            return found
        log.error("Auto-detect enabled but no Arduino port found.")
        sys.exit(1)
    env_port = os.getenv(f"{ENV_KEY_PREFIX}SERIAL_PORT")
    if env_port:
        return env_port
    return "/dev/ttyArduino"


class BlockParser:
    def __init__(self):
        self._buf = ""
        self.last_count = None
        self.last_beam = None
        self.trigger_count = 0
        self.last_event_count = 0
        self.pending_trigger_count = 0
        self._first_count_after_connection = True

    def feed(self, data):
        self._buf += data
        blocks = []
        while True:
            block = self._try_extract()
            if block is None:
                break
            parsed = self._parse(block)
            if parsed:
                blocks.append(parsed)
        return blocks

    def _try_extract(self):
        lines = self._buf.split("\n")
        count_idx = None
        for i, line in enumerate(lines):
            if line.strip().startswith("Count:"):
                count_idx = i
                break
        if count_idx is None:
            self._buf = "\n".join(lines[-5:])
            return None
        if len(lines) < count_idx + 4:
            return None
        beam = lines[count_idx + 1].strip()
        temp = lines[count_idx + 2].strip()
        hum = lines[count_idx + 3].strip()
        if not (beam.startswith("Beam:") and temp.startswith("Temp:") and hum.startswith("Humidity:")):
            self._buf = "\n".join(lines[count_idx + 1:])
            return None
        # Relay line is optional (absent on firmware predating fan control).
        block_lines = 4
        if len(lines) > count_idx + 4 and lines[count_idx + 4].strip().startswith("Relay:"):
            block_lines = 5
        block = "\n".join(lines[count_idx:count_idx + block_lines])
        self._buf = "\n".join(lines[count_idx + block_lines:])
        return block

    def _parse(self, block):
        lines = block.strip().split("\n")
        result = {"count": None, "beam": None, "temp": None, "humidity": None,
                  "relay": None, "relay_safety": False}
        for line in lines:
            line = line.strip()
            if line.startswith("Count:"):
                try:
                    result["count"] = int(line.split(":", 1)[1].strip())
                except (ValueError, IndexError):
                    pass
            elif line.startswith("Beam:"):
                result["beam"] = line.split(":", 1)[1].strip()
            elif line.startswith("Temp:"):
                val = line.split(":", 1)[1].strip()
                if val.endswith("C"):
                    val = val[:-1].strip()
                    try:
                        result["temp"] = round(float(val), 2)
                    except ValueError:
                        pass
            elif line.startswith("Humidity:"):
                val = line.split(":", 1)[1].strip()
                if val.endswith("%"):
                    val = val[:-1].strip()
                    try:
                        result["humidity"] = round(float(val), 2)
                    except ValueError:
                        pass
            elif line.startswith("Relay:"):
                # "OFF (SAFETY)" => status off + safety flag (distinct from a
                # plain OFF, so a manual-ON that is safety-blocked is not
                # confused with a user turning the fan off).
                val = line.split(":", 1)[1].strip()
                result["relay_safety"] = "SAFETY" in val.upper()
                val = val.split("(")[0].strip().upper()
                if val in ("ON", "OFF"):
                    result["relay"] = val.lower()
        previous_count = self.last_count
        beam = result.get("beam")
        count_decreased = False
        current_count = result.get("count") or 0
        first_count = self._first_count_after_connection or previous_count is None
        if first_count:
            self.trigger_count = 0
            self.last_event_count = 0
            self.pending_trigger_count = 0
            self.last_beam = beam
            self._first_count_after_connection = False
        # The Arduino reports a cumulative counter, but a decrease is an
        # explicit resync (e.g. user override/reset), not a negative delta.
        # Reset the event baseline so the next increment is forwarded.
        if previous_count is not None and current_count < previous_count:
            count_decreased = True
            self.trigger_count = 0
            self.last_event_count = 0
            self.pending_trigger_count = 0
            self.last_beam = None
        if not first_count and not count_decreased and beam == "BROKEN" and self.last_beam != "BROKEN":
            self.trigger_count += 1
            self.pending_trigger_count += 1
        result["trigger_count"] = self.trigger_count
        count_delta = 0 if first_count else max(0, current_count - (previous_count or 0))
        result["new_trigger_count"] = max(count_delta, self.pending_trigger_count)
        if count_decreased:
            result["new_trigger_count"] = 0
        self.pending_trigger_count = max(0, self.pending_trigger_count - count_delta)
        self.last_count = result.get("count")
        self.last_beam = beam
        result["baseline_only"] = first_count
        return result


def build_payload(parsed, dht_serial, ir_serial, relay_serial):
    readings = []
    if parsed["temp"] is not None and parsed["humidity"] is not None:
        readings.append({
            "serial_number": dht_serial,
            "temperature_c": parsed["temp"],
            "humidity_pct": parsed["humidity"],
        })
    if parsed["count"] is not None:
        readings.append({
            "serial_number": ir_serial,
            "count": parsed["count"],
            "trigger_count": parsed.get("trigger_count", parsed["count"]),
            "new_trigger_count": parsed.get("new_trigger_count", 0),
        })
    if parsed["relay"] is not None:
        readings.append({
            "serial_number": relay_serial,
            "relay_status": parsed["relay"],
            "relay_safety": parsed["relay_safety"],
        })
    if not readings:
        return None
    return {
        "readings": readings,
        "recorded_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00"),
    }


def fetch_relay_command(session, url):
    """Poll the intended relay state + AUTO thresholds.

    Returns {"mode","command","on_temp","off_temp"} or None.

    None means either the API is unreachable or no relay is registered for
    this device — the bridge simply stays idle until that changes.
    """
    try:
        resp = session.get(url, timeout=5, allow_redirects=False)
        if resp.status_code == 200:
            relay = resp.json().get("relay")
            if relay is None:
                return None
            return {
                "mode": relay.get("mode"),
                "command": relay.get("command"),
                "on_temp": relay.get("on_temp"),
                "off_temp": relay.get("off_temp"),
            }
        log.error("Command API failure url=%s HTTP %d response=%r",
                  url, resp.status_code, resp.text[:240])
    except (requests.RequestException, ValueError) as e:
        log.error("Command fetch failed url=%s error=%s", url, e)
    return None


def threshold_command(on_temp, off_temp):
    """Format the THRESH serial command, or None when thresholds are absent."""
    if on_temp is None or off_temp is None:
        return None
    return f"THRESH:{float(on_temp):.1f}:{float(off_temp):.1f}"


def apply_commands(ser, intended, last_applied_command, last_reported_relay,
                   last_reported_safety, last_applied_threshold):
    """Write THRESH/RELAY serial commands for the polled intended state.

    Both are written only on change (or after a per-connection reset), mirroring
    the polled intended-state design — nothing is spammed every poll cycle.
    Returns (last_applied_command, last_applied_threshold).
    """
    # THRESH — app-configured AUTO hysteresis thresholds, written only when they
    # differ from what we last wrote. last_applied_threshold is reset to None
    # per serial connection, so a bridge restart / Arduino reboot re-applies it
    # (the Arduino reverted to its boot-time 35C/30C defaults).
    thresh_line = threshold_command(intended.get("on_temp"), intended.get("off_temp"))
    if thresh_line is not None and thresh_line != last_applied_threshold:
        ser.write((thresh_line + "\n").encode())
        log.info("Wrote serial command: %s", thresh_line)
        last_applied_threshold = thresh_line

    # RELAY command — re-apply on change or when a manual override no longer
    # matches the reported state (Arduino reboot that reverted to hysteresis).
    target = (intended["mode"], intended["command"])
    needs_send = False
    if target != last_applied_command:
        needs_send = True
    elif (intended["mode"] == "manual"
          and not last_reported_safety
          and last_reported_relay is not None
          and last_reported_relay != intended["command"]):
        # Reported state drifted from the manual override (e.g. Arduino rebooted).
        # Re-apply it. Skipped while the safety default is blocking the relay —
        # "OFF (SAFETY)" is the correct response to a manual ON, not a drift to
        # be corrected.
        needs_send = True
    if needs_send:
        cmd_line = f"RELAY:{intended['command'].upper()}\n"
        ser.write(cmd_line.encode())
        log.info("Wrote serial command: %s", cmd_line.strip())
        last_applied_command = target

    return last_applied_command, last_applied_threshold


log = logging.getLogger("bridge")


def run_loop(args, config=None):
    device_key = args.device_key or os.getenv(f"{ENV_KEY_PREFIX}DEVICE_KEY")
    if not device_key:
        log.error("Device key required. Set --device-key or LAYrate_DEVICE_KEY.")
        sys.exit(1)

    base_url = resolve_api_base_url(args.api_base_url, config or {})
    api_url = api_endpoint(base_url, "api/sensor-readings")
    command_url = api_endpoint(base_url, "api/relay/command")
    spool_path = args.spool_path or os.getenv("LAYRATE_DELIVERY_SPOOL", DEFAULT_SPOOL_PATH)
    heartbeat_path = Path(args.heartbeat_state)
    heartbeat_server = start_heartbeat_server(args.heartbeat_host, args.heartbeat_port, heartbeat_path)
    heartbeat = BridgeHeartbeat(heartbeat_path)
    log.info("DHT serial: %s | IR serial: %s | Relay serial: %s",
             args.dht_serial, args.ir_serial, args.relay_serial)

    session = requests.Session()
    session.headers.update({
        "X-Device-Key": device_key,
        "Content-Type": "application/json",
        "Accept": "application/json",
    })

    log.info("API base URL: %s | sensor URL: %s | command URL: %s", base_url, api_url, command_url)
    log.info("Delivery spool: %s", spool_path)
    spool = DeliverySpool(spool_path)
    try:
        spool.path.parent.mkdir(parents=True, exist_ok=True)
        fd, probe_path = tempfile.mkstemp(dir=spool.path.parent)
        os.close(fd)
        os.unlink(probe_path)
    except OSError as exc:
        heartbeat_server.shutdown()
        raise RuntimeError(f"delivery spool directory is not writable: {spool.path.parent}: {exc}") from exc
    dead_letter_dir = Path(args.dead_letter_dir or (spool.path.parent / "dead-letter"))
    try:
        dead_letter_dir.mkdir(parents=True, exist_ok=True)
        fd, probe_path = tempfile.mkstemp(dir=dead_letter_dir)
        os.close(fd)
        os.unlink(probe_path)
    except OSError as exc:
        heartbeat_server.shutdown()
        raise RuntimeError(f"dead-letter directory is not writable: {dead_letter_dir}: {exc}") from exc
    try:
        probe = session.get(command_url, timeout=5, allow_redirects=False)
        if probe.status_code != 200 or "application/json" not in probe.headers.get("Content-Type", "").lower():
            raise RuntimeError(f"startup API check failed url={command_url} HTTP {probe.status_code} response={probe.text[:240]!r}")
        probe_body = probe.json()
        if not isinstance(probe_body, dict) or "relay" not in probe_body:
            raise RuntimeError(f"startup API returned unexpected JSON url={command_url} response={probe.text[:240]!r}")
    except (requests.RequestException, ValueError, RuntimeError, OSError) as exc:
        log.critical("Startup API self-check failed: %s", exc)
        heartbeat_server.shutdown()
        raise

    delivery = DeliveryClient(session, api_url, spool, dead_letter_dir=dead_letter_dir)
    parser = BlockParser()
    serial_activity_at = None
    delivery_at = None
    delivery_error = None
    last_heartbeat = time.monotonic()

    def persist_health(serial_connected):
        sent, failed = delivery.stats()
        heartbeat.update_counts(sent, failed, spool.count())
        with heartbeat.lock:
            heartbeat.serial_connected = serial_connected
            heartbeat.serial_activity_at = serial_activity_at
            heartbeat.delivery_at = delivery_at
            heartbeat.delivery_error = delivery_error
        write_heartbeat_http_state(
            heartbeat_path, serial_connected, serial_activity_at, delivery_at,
            delivery_error, sent, failed, spool.count(),
        )

    delivery.replay_spool()
    if spool.count() == 0:
        delivery_at = datetime.now(timezone.utc).isoformat()
    persist_health(False)

    while True:
        port = args.port or resolve_port(None, args.auto_port)
        try:
            with serial.Serial(port, args.baud, timeout=1) as ser:
                ser.reset_input_buffer()
                parser = BlockParser()
                log.info("Serial connection opened on %s", port)
                if serial_activity_at is None:
                    serial_activity_at = delivery_at
                persist_health(True)

                # Relay command channel state — reset per connection because the
                # Arduino may have rebooted while we were disconnected. Resetting
                # last_applied_threshold forces the app-configured AUTO thresholds
                # to be re-sent (the Arduino reverted to boot-time defaults).
                last_applied_command = None
                last_applied_threshold = None
                last_reported_relay = None
                last_reported_safety = False
                last_command_poll = 0.0

                # ── Settling period ────────────────────────────────────────
                # Discard all output for the first N seconds after opening
                # the port.  The Arduino Uno resets on DTR toggle, then
                # runs setup() which prints Count: 0 and potentially stale
                # sensor values before valid readings begin.
                settling_end = time.monotonic() + args.settle_seconds
                log.info("Settling for %d seconds (discarding Arduino boot output)...",
                         args.settle_seconds)

                while True:
                    now_mono = time.monotonic()
                    if settling_end is not None and now_mono >= settling_end:
                        log.info("Settling period complete — forwarding readings.")
                        settling_end = None

                    # ── Relay command channel (bidirectional) ──
                    # Poll the intended state after settling and write it to the
                    # Arduino whenever it differs from what we last applied, or
                    # when a manual override no longer matches the reported state
                    # (covers an Arduino reboot that reverted to hysteresis).
                    if settling_end is None and now_mono - last_command_poll >= COMMAND_POLL_INTERVAL:
                        last_command_poll = now_mono
                        intended = fetch_relay_command(session, command_url)
                        if intended is not None:
                            (last_applied_command, last_applied_threshold) = apply_commands(
                                ser, intended, last_applied_command, last_reported_relay,
                                last_reported_safety, last_applied_threshold)

                    if ser.in_waiting:
                        data = ser.read(ser.in_waiting).decode("utf-8", errors="replace")
                        if data:
                            serial_activity_at = datetime.now(timezone.utc).isoformat()
                            persist_health(True)
                        blocks = parser.feed(data)
                        for parsed in blocks:
                            if parsed.get("relay") is not None:
                                last_reported_relay = parsed["relay"]
                                last_reported_safety = parsed.get("relay_safety", False)

                            if settling_end is not None:
                                log.debug("Settling: discarding block (count=%s, temp=%s, hum=%s)",
                                          parsed.get("count"), parsed.get("temp"), parsed.get("humidity"))
                                continue

                            log.debug("Parsed: count=%s  temp=%s  hum=%s  relay=%s  safety=%s",
                                      parsed["count"], parsed["temp"], parsed["humidity"],
                                      parsed["relay"], parsed["relay_safety"])
                            if parsed.get("baseline_only") and parsed.get("count") is not None:
                                parsed = dict(parsed)
                                parsed["new_trigger_count"] = 0
                                parsed["trigger_count"] = 0
                            payload = build_payload(parsed, args.dht_serial, args.ir_serial, args.relay_serial)
                            if payload is None:
                                continue
                            payload["sequence_id"] = str(uuid.uuid4())
                            delivered = delivery.deliver(payload)
                            if delivered:
                                delivery_at = datetime.now(timezone.utc).isoformat()
                                delivery_error = None
                                log.info("Delivered sequence_id=%s readings=%d url=%s HTTP 200",
                                         payload["sequence_id"], len(payload["readings"]), api_url)
                                if parsed.get("count") is not None:
                                    parser.last_event_count = parsed["count"]
                            else:
                                delivery_error = "API delivery failed; payload queued"
                            persist_health(True)
                    else:
                        time.sleep(POLL_INTERVAL)
                    if time.monotonic() - last_heartbeat >= HEARTBEAT_INTERVAL:
                        last_heartbeat = time.monotonic()
                        delivery.replay_spool()
                        log.info("Bridge heartbeat serial_connected=true serial_activity_at=%s delivery_at=%s sent=%d failed=%d queued=%d",
                                 serial_activity_at, delivery_at, *delivery.stats(), spool.count())
                        persist_health(True)
        except serial.SerialException as e:
            delivery_error = f"serial_error: {e}"
            persist_health(False)
            log.error("Serial error port=%s: %s — re-resolving in %ds...", port, e, RECONNECT_DELAY)
            time.sleep(RECONNECT_DELAY)
        except OSError as e:
            delivery_error = f"serial_io_error: {e}"
            persist_health(False)
            log.error("Serial I/O error port=%s: %s — re-resolving in %ds...", port, e, RECONNECT_DELAY)
            time.sleep(RECONNECT_DELAY)
        except KeyboardInterrupt:
            log.info("Shutting down.")
            heartbeat_server.shutdown()
            break
        except Exception:
            log.exception("Unexpected bridge loop failure; reconnecting in %ds", RECONNECT_DELAY)
            delivery_error = "unexpected bridge loop failure"
            persist_health(False)
            time.sleep(RECONNECT_DELAY)


def merge_config(args):
    """Load --config JSON and overlay CLI args on top (CLI wins)."""
    config_path = args.config
    if config_path and os.path.exists(config_path):
        with open(config_path) as f:
            cfg = json.load(f)
        args._config = cfg
        for key, val in cfg.items():
            if hasattr(args, key) and getattr(args, key) is None and val is not None:
                setattr(args, key, val)
        log.info("Loaded config from %s", config_path)
    if getattr(args, "dead_letter_dir", None) is None:
        args.dead_letter_dir = os.getenv("LAYRATE_DEAD_LETTER_DIR")
    return args


def main():
    parser = argparse.ArgumentParser(description="LayRate Serial Bridge")
    parser.add_argument("--config", default=None,
                        help="Path to JSON config file (CLI args override config)")
    parser.add_argument("--port", default=None, help="Serial port (e.g. /dev/ttyACM0)")
    parser.add_argument("--baud", type=int, default=DEFAULT_BAUD)
    parser.add_argument("--api-base-url", default=None,
                        help="LayRate API base URL (or LAYRATE_API_BASE_URL); default is http://127.0.0.1")
    parser.add_argument("--device-key", default=None, help="X-Device-Key header value")
    parser.add_argument("--dht-serial", default=None, help="DHT22 sensor serial number")
    parser.add_argument("--ir-serial", default=None, help="IR breakbeam sensor serial number")
    parser.add_argument("--relay-serial", default=None, help="Relay device serial number")
    parser.add_argument("--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"])
    parser.add_argument("--settle-seconds", type=float, default=5.0,
                        help="Seconds of Arduino output to discard after serial open (DTR reset compensation)")
    parser.add_argument("--auto-port", action="store_true", help="Auto-detect Arduino port")
    parser.add_argument("--spool-path", default=None, help=argparse.SUPPRESS)
    parser.add_argument("--dead-letter-dir", default=None, help=argparse.SUPPRESS)
    parser.add_argument("--heartbeat-host", default="127.0.0.1", help=argparse.SUPPRESS)
    parser.add_argument("--heartbeat-port", type=int, default=8765, help=argparse.SUPPRESS)
    parser.add_argument("--heartbeat-state", default="/run/layrate-serial/heartbeat.json", help=argparse.SUPPRESS)
    args = parser.parse_args()

    logging.basicConfig(
        level=getattr(logging, args.log_level),
        format="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%S",
    )

    args = merge_config(args)

    if not args.dht_serial:
        args.dht_serial = "DHT22-001"
    if not args.ir_serial:
        args.ir_serial = "IRBBS-001"
    if not args.relay_serial:
        args.relay_serial = "RELAY-001"

    run_loop(args, config=getattr(args, "_config", {}))


if __name__ == "__main__":
    main()
