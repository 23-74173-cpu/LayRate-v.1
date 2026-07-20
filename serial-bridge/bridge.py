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

Runs as a systemd service on the Raspberry Pi.
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
POLL_INTERVAL = 0.05
RECONNECT_DELAY = 5
ENV_KEY_PREFIX = "LAYrate_"


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
    return "/dev/ttyACM0"


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
        block = "\n".join(lines[count_idx:count_idx + 4])
        self._buf = "\n".join(lines[count_idx + 4:])
        return block

    def _parse(self, block):
        lines = block.strip().split("\n")
        result = {"count": None, "beam": None, "temp": None, "humidity": None}
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
        return result


def build_payload(parsed, dht_serial, ir_serial):
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
    if not readings:
        return None
    return {
        "readings": readings,
        "recorded_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00"),
    }


log = logging.getLogger("bridge")


def run_loop(args):
    device_key = args.device_key or os.getenv(f"{ENV_KEY_PREFIX}DEVICE_KEY")
    if not device_key:
        log.error("Device key required. Set --device-key or LAYrate_DEVICE_KEY.")
        sys.exit(1)

    port = resolve_port(args.port, args.auto_port)
    log.info("Target port: %s @ %d baud", port, args.baud)
    log.info("API URL: %s", args.api_url)
    log.info("DHT serial: %s | IR serial: %s", args.dht_serial, args.ir_serial)

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
                while True:
                    if ser.in_waiting:
                        data = ser.read(ser.in_waiting).decode("utf-8", errors="replace")
                        blocks = parser.feed(data)
                        for parsed in blocks:
                            log.debug("Parsed: count=%s  temp=%s  hum=%s",
                                      parsed["count"], parsed["temp"], parsed["humidity"])
                            payload = build_payload(parsed, args.dht_serial, args.ir_serial)
                            if payload is None:
                                continue
                            try:
                                resp = session.post(args.api_url, json=payload, timeout=10)
                                if resp.status_code in (200, 207):
                                    log.info("Sent %d reading(s) to %s (HTTP %d)",
                                             len(payload["readings"]), args.api_url, resp.status_code)
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


def main():
    parser = argparse.ArgumentParser(description="LayRate Serial Bridge")
    parser.add_argument("--port", help="Serial port (e.g. /dev/ttyACM0)")
    parser.add_argument("--baud", type=int, default=DEFAULT_BAUD)
    parser.add_argument("--api-url", default=DEFAULT_API_URL)
    parser.add_argument("--device-key", help="X-Device-Key header value")
    parser.add_argument("--dht-serial", default="DHT22-001")
    parser.add_argument("--ir-serial", default="IRBBS-001")
    parser.add_argument("--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"])
    parser.add_argument("--auto-port", action="store_true", help="Auto-detect Arduino port")
    args = parser.parse_args()

    logging.basicConfig(
        level=getattr(logging, args.log_level),
        format="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%S",
    )

    run_loop(args)


if __name__ == "__main__":
    main()
