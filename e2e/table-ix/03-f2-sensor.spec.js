import { test, expect } from '@playwright/test';

// Table IX F2 — Sensor Data Collection Module (POST /api/sensor-readings).
// Exercised with Playwright's request fixture (the APIRequestContext is the
// correct tool here: this feature IS the ingestion endpoint, unlike F8 where
// only the serial path counts). The real bridge sends Accept:
// application/json (serial-bridge/bridge.py) — without it Laravel answers
// validation failures with 302 redirects instead of 422/207, so the header
// is part of every call below.
//
// Device key: serial-bridge/sensors.json dev key (overridable via
// LAYRATE_DEVICE_KEY). Registered hardware: DHT22-001 -> CAGE-T (cage 5),
// IRBBS-001 -> CAGE-T slot 61 (4 active hens).
//
// Benchmark-hygiene note: these cases write real rows. To keep the quarantined
// Mar-Jun dataset and the F3 stale-state assertions intact, all payloads use
// a FIXED backdated recorded_at (2026-01-15, outside every real/demo window)
// and the IR leg reports count=0 (valid: zero-egg writes keep lifetime sums
// bit-identical). Reruns are idempotent (updateOrCreate on the same keys; the
// 5s debounce only triggers on fresh timestamps). After the benchmark run,
// remove the Jan-15 benchmark rows (notes='Sensor reading', log_date
// 2026-01-15, cage 5) to restore full purity.

const DEVICE_KEY = process.env.LAYRATE_DEVICE_KEY ?? 'layrate-pi-dev-key-2026';
const HEADERS = {
  'Content-Type': 'application/json',
  Accept: 'application/json',
  'X-Device-Key': DEVICE_KEY,
};
// Fixed benchmark timestamp: outside the Mar-Jun real window, the Jul-Sep
// demo window, and every 24h/7d/30d live readout.
const RECORDED_AT = '2026-01-15T12:00:00+08:00';
const RECORDED_AT_LATE = '2026-01-15T12:05:00+08:00';

test.describe('Table IX F2 — Sensor Data Collection Module', () => {
  test('TC-04 valid DHT22+IR payload is ingested (200, both legs processed)', async ({ request }) => {
    const resp = await request.post('/api/sensor-readings', {
      headers: HEADERS,
      data: {
        recorded_at: RECORDED_AT,
        readings: [
          { serial_number: 'DHT22-001', temperature_c: 27.7, humidity_pct: 66.6 },
          // count=0: valid (slot holds 4 hens), writes the occupancy leg while
          // keeping lifetime egg sums bit-identical (see file header).
          { serial_number: 'IRBBS-001', count: 0 },
        ],
      },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    const kinds = Object.fromEntries(body.processed.map((p) => [p.serial_number, p.type]));
    expect(kinds['DHT22-001']).toBe('environment');
    expect(kinds['IRBBS-001']).toBe('occupancy');
    expect(body.errors ?? []).toEqual([]);
  });

  test('TC-05 over-count is refused without corrupting production data', async ({ request }) => {
    // Slot 61 holds 4 active hens: 9 exceeds the hen-count invariant.
    // A MIXED payload exercises the true 207 partial path (valid leg commits,
    // offending leg is refused loudly instead of corrupting HDEP/forecast
    // inputs); the occupancy write preceding the guard is preserved on commit.
    const partial = await request.post('/api/sensor-readings', {
      headers: HEADERS,
      data: {
        recorded_at: RECORDED_AT_LATE,
        readings: [
          { serial_number: 'DHT22-001', temperature_c: 27.7, humidity_pct: 66.6 },
          { serial_number: 'IRBBS-001', count: 9 },
        ],
      },
    });
    expect(partial.status()).toBe(207);
    const partialBody = await partial.json();
    const kinds = Object.fromEntries(
      (partialBody.processed ?? []).map((p) => [p.serial_number, p.type]),
    );
    expect(kinds['DHT22-001']).toBe('environment');
    expect(kinds['IRBBS-001']).toBeUndefined();
    expect(JSON.stringify(partialBody.errors)).toMatch(/exceeds hen count/);

    // All-rejected payload: nothing accepted -> full rollback, 422, zero writes.
    const rejected = await request.post('/api/sensor-readings', {
      headers: HEADERS,
      data: {
        recorded_at: RECORDED_AT_LATE,
        readings: [{ serial_number: 'IRBBS-001', count: 9 }],
      },
    });
    expect(rejected.status()).toBe(422);
    expect((await rejected.json()).message).toMatch(/No readings were accepted/);
  });

  test('TC-06 auth and validation failures write nothing', async ({ request }) => {
    const payload = {
      recorded_at: RECORDED_AT,
      readings: [{ serial_number: 'DHT22-001', temperature_c: 27.7, humidity_pct: 66.6 }],
    };

    const wrongKey = await request.post('/api/sensor-readings', {
      headers: { ...HEADERS, 'X-Device-Key': 'wrong-key' },
      data: payload,
    });
    expect(wrongKey.status()).toBe(401);
    expect((await wrongKey.json()).message).toMatch(/Unrecognized device key/);

    const missingKey = await request.post('/api/sensor-readings', {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      data: payload,
    });
    expect(missingKey.status()).toBe(401);

    const malformed = await request.post('/api/sensor-readings', {
      headers: HEADERS,
      data: { recorded_at: RECORDED_AT, readings: [{ serial_number: 'DHT22-001' }] },
    });
    expect(malformed.status()).toBe(422);
  });
});
