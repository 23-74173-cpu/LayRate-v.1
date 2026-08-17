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
import sys
import time
from datetime import datetime, timezone

import requests
import serial
from serial.tools import list_ports

DEFAULT_BAUD = 9600
DEFAULT_API_URL = "http://localhost/api/sensor-readings"
DEFAULT_COMMAND_URL = "http://localhost/api/relay/command"
POLL_INTERVAL = 0.05
COMMAND_POLL_INTERVAL = 2.0
RECONNECT_DELAY = 5
ENV_KEY_PREFIX = "LAYrate_"
DEFAULT_CONFIG_PATH = os.path.join(os.path.dirname(__file__), "sensors.json")


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
        resp = session.get(url, timeout=5)
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
        log.warning("Command API HTTP %d: %s", resp.status_code, resp.text[:200])
    except (requests.RequestException, ValueError) as e:
        log.error("Command fetch failed: %s", e)
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


def run_loop(args):
    device_key = args.device_key or os.getenv(f"{ENV_KEY_PREFIX}DEVICE_KEY")
    if not device_key:
        log.error("Device key required. Set --device-key or LAYrate_DEVICE_KEY.")
        sys.exit(1)

    port = resolve_port(args.port, args.auto_port)
    log.info("Target port: %s @ %d baud", port, args.baud)
    log.info("API URL: %s", args.api_url)
    log.info("DHT serial: %s | IR serial: %s | Relay serial: %s",
             args.dht_serial, args.ir_serial, args.relay_serial)

    parser = BlockParser()
    session = requests.Session()
    session.headers.update({
        "X-Device-Key": device_key,
        "Content-Type": "application/json",
        "Accept": "application/json",
    })

    while True:
        try:
            with serial.Serial(port, args.baud, timeout=1) as ser:
                ser.reset_input_buffer()
                log.info("Serial connection opened on %s", port)

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
                        intended = fetch_relay_command(session, args.command_api_url)
                        if intended is not None:
                            (last_applied_command, last_applied_threshold) = apply_commands(
                                ser, intended, last_applied_command, last_reported_relay,
                                last_reported_safety, last_applied_threshold)

                    if ser.in_waiting:
                        data = ser.read(ser.in_waiting).decode("utf-8", errors="replace")
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
                            payload = build_payload(parsed, args.dht_serial, args.ir_serial, args.relay_serial)
                            if payload is None:
                                continue
                            try:
                                resp = session.post(args.api_url, json=payload, timeout=10)
                                if resp.status_code == 200:
                                    log.info("Sent %d reading(s) to %s (HTTP 200)",
                                             len(payload["readings"]), args.api_url)
                                elif resp.status_code == 207:
                                    # Partial success — the server accepted some readings and
                                    # rejected others (bad serial, missing fields, sensor-reset
                                    # guard, ...). Previously this logged identically to a clean
                                    # 200, so a reading silently failing server-side was
                                    # invisible here. Surface the per-reading errors instead.
                                    try:
                                        body = resp.json()
                                    except ValueError:
                                        body = {}
                                    log.warning("Partial failure sending to %s (HTTP 207): %s",
                                                args.api_url, body.get("errors", body))
                                else:
                                    log.warning("API HTTP %d: %s", resp.status_code, resp.text[:200])
                            except requests.RequestException as e:
                                log.error("HTTP request failed: %s", e)
                    else:
                        time.sleep(POLL_INTERVAL)
        except serial.SerialException as e:
            log.error("Serial error: %s — retrying in %ds...", e, RECONNECT_DELAY)
            time.sleep(RECONNECT_DELAY)
        except KeyboardInterrupt:
            log.info("Shutting down.")
            break


def merge_config(args):
    """Load --config JSON and overlay CLI args on top (CLI wins)."""
    config_path = args.config
    if config_path and os.path.exists(config_path):
        with open(config_path) as f:
            cfg = json.load(f)
        for key, val in cfg.items():
            if hasattr(args, key) and getattr(args, key) is None and val is not None:
                setattr(args, key, val)
        log.info("Loaded config from %s", config_path)
    return args


def main():
    parser = argparse.ArgumentParser(description="LayRate Serial Bridge")
    parser.add_argument("--config", default=None,
                        help="Path to JSON config file (CLI args override config)")
    parser.add_argument("--port", default=None, help="Serial port (e.g. /dev/ttyACM0)")
    parser.add_argument("--baud", type=int, default=DEFAULT_BAUD)
    parser.add_argument("--api-url", default=DEFAULT_API_URL)
    parser.add_argument("--device-key", default=None, help="X-Device-Key header value")
    parser.add_argument("--dht-serial", default=None, help="DHT22 sensor serial number")
    parser.add_argument("--ir-serial", default=None, help="IR breakbeam sensor serial number")
    parser.add_argument("--relay-serial", default=None, help="Relay device serial number")
    parser.add_argument("--command-api-url", default=DEFAULT_COMMAND_URL,
                        help="Relay command endpoint to poll (default: %(default)s)")
    parser.add_argument("--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"])
    parser.add_argument("--settle-seconds", type=float, default=5.0,
                        help="Seconds of Arduino output to discard after serial open (DTR reset compensation)")
    parser.add_argument("--auto-port", action="store_true", help="Auto-detect Arduino port")
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

    run_loop(args)


if __name__ == "__main__":
    main()
