#!/usr/bin/env python3
"""Calibration metrics from a hand-filled template CSV.

    python3 compute_metrics.py --in dht22_calibration_filled.csv [--out results.md]

Per (sensor unit, variable): paired-reading count, MAE, max deviation, and
the Table V descriptive equivalent. Plus pooled Overall rows per variable
and a manufacturer-tolerance verdict per row for the discussion section.

Table V bands (user-supplied, implemented as ladders so boundary values such
as 0.505 fall deterministically; reported to 2 decimals):
  Temp MAE: <=0.50 Outstanding (within datasheet tolerance); <=1.00 Very
    Satisfactory; <=2.00 Satisfactory; <=3.00 Needs Improvement; else
    Needs Redevelopment.
  Humidity MAE: <=2.00 Outstanding (within datasheet tolerance); <=5.00 Very
    Satisfactory; <=8.00 Satisfactory; <=10.00 Needs Improvement; else
    Needs Redevelopment.
Rows missing either side are dropped from pairing (counted in the report).
"""
import argparse
import csv
import sys
from collections import defaultdict


def temp_band(mae):
    if mae <= 0.50:
        return "Outstanding (within datasheet tolerance)"
    if mae <= 1.00:
        return "Very Satisfactory"
    if mae <= 2.00:
        return "Satisfactory"
    if mae <= 3.00:
        return "Needs Improvement"
    return "Needs Redevelopment"


def hum_band(mae):
    if mae <= 2.00:
        return "Outstanding (within datasheet tolerance)"
    if mae <= 5.00:
        return "Very Satisfactory"
    if mae <= 8.00:
        return "Satisfactory"
    if mae <= 10.00:
        return "Needs Improvement"
    return "Needs Redevelopment"


def temp_spec(mae):
    return ("within the ±0.5 °C manufacturer tolerance"
            if mae <= 0.5 else "OUTSIDE the ±0.5 °C manufacturer tolerance")


def hum_spec(mae):
    if mae <= 2.0:
        return "within the strict ±2 %RH manufacturer tolerance"
    if mae <= 5.0:
        return "within the lenient ±5 %RH manufacturer tolerance"
    return "OUTSIDE the ±2–5 %RH manufacturer tolerance"


VARIABLES = [
    ("Temperature (°C)", "DHT22 Temp (°C)", "Reference Temp (°C)", temp_band, temp_spec),
    ("Humidity (%RH)", "DHT22 Humidity (%RH)", "Reference Humidity (%RH)", hum_band, hum_spec),
]


def fnum(value):
    try:
        return float(str(value).strip())
    except (ValueError, AttributeError):
        return None


def summarize(pairs):
    devs = [abs(s - r) for s, r in pairs]
    mae = sum(devs) / len(devs)
    return len(pairs), mae, max(devs)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--in", dest="input", required=True,
                        help="Filled template CSV (reference columns completed)")
    parser.add_argument("--out", default=None, help="Write markdown table here (else stdout only)")
    args = parser.parse_args(argv)

    with open(args.input, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    units = []
    for r in rows:
        if r.get("Sensor Unit") not in units:
            units.append(r["Sensor Unit"])

    by_unit_var = defaultdict(list)   # (unit, var) -> [(sensor, ref)]
    pooled_var = defaultdict(list)    # var -> [(sensor, ref)]
    dropped = 0
    for r in rows:
        for var, scol, rcol, _, _ in VARIABLES:
            s, ref = fnum(r.get(scol)), fnum(r.get(rcol))
            if s is None or ref is None:
                dropped += 1
                continue
            by_unit_var[(r["Sensor Unit"], var)].append((s, ref))
            pooled_var[var].append((s, ref))

    if not pooled_var:
        raise RuntimeError("No complete sensor/reference pairs found.")

    table = ["| Sensor Unit | Measured Variable | Paired Readings | MAE | Max. Deviation | Descriptive Equivalent |",
             "|---|---|---|---|---|---|"]
    verdicts = []
    for unit in units + ["Overall"]:
        for var, _, _, band, spec in VARIABLES:
            pairs = pooled_var[var] if unit == "Overall" else by_unit_var.get((unit, var), [])
            if not pairs:
                continue
            n, mae, mx = summarize(pairs)
            table.append(f"| {unit} | {var} | {n} | {mae:.2f} | {mx:.2f} | {band(mae)} |")
            verdicts.append(f"- {unit} {var}: MAE {mae:.2f} — {spec(mae)}.")

    out = "\n".join(table)
    out += "\n\n**Manufacturer tolerance (discussion):**\n" + "\n".join(verdicts) + "\n"
    if dropped:
        out += (f"\n_Note: {dropped} incomplete cells were excluded from pairing._\n")

    if args.out:
        with open(args.out, "w", encoding="utf-8") as f:
            f.write(out + "\n")
        print(f"wrote {args.out}")
    print(out)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
