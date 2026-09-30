# LayRate Postman / Newman API benchmark (Table IX API leg)

30 test cases (TC-API-01…30) across 6 collections, mirroring the manuscript's
API evaluation design. Run each collection **3 independent times** for
consistency; aggregate per the user's reporting template afterwards.

## Run

```sh
cd postman
./run-newman.sh [env-file] [base-url] [device-key]
# e.g. local validation:
./run-newman.sh environments/layrate-pi.json http://127.0.0.1:8000 layrate-pi-dev-key-2026
# Pi (fill secrets via --env-var, never commit them):
./run-newman.sh environments/layrate-pi.json http://LayRatePI.local \
  --env-var ... # (edit run-newman.sh EXTRA_ARGS or export first)
```

Requirements: Newman ≥ 6 (`npm install -g newman`). Only the built-in
`json,cli` reporters are used (no htmlextra dependency). Reports land in
`postman/results/<timestamp>/` (18 JSON files).

Before pointing at the Pi, fill in `environments/layrate-pi.json`
(`deviceKey`, admin/operator credentials) or pass `--env-var` overrides.
Prerequisites on the target: ≥1 cage (default id 1), DHT22/IR hardware
registered under the `dhtSerial`/`irSerial` convention, seed users present.

## Design constraints discovered during validation (read before editing)

- **Login pattern**: collection pre-request logs in once (`authed` flag);
  web POSTs re-scrape a fresh CSRF token from a live page first — tokens
  scraped pre-login go stale and 419. Follow this pattern for any new
  session POST.
- **No `followRedirects:false`**: ignored by Newman ≤ 6.2 — redirect cases
  assert the *followed* outcome (login/dashboard markers), never 302/Location.
- **No `pm.cookies.jar().clear()`**: throws in this Newman (`requires a valid
  url` regardless of form). Unauthenticated cases run FIRST with a
  request-name guard that skips the auto-login.
- **No nested `{{var}}` in collection variable values** — they stay literal.
  Indirection goes through `loginRole` + direct env lookups.
- **Whole-second `recorded_at` only** (collection 1 pins `T12:00:00Z` /
  `T12:05:00Z`): MySQL DATETIME truncates millis, so two ms-precision posts
  in the same second collide on the unique key → 500 (filed as backlog
  Prompt 17).

## Write footprint (clean up after Pi runs)

- Collection 1 (200/207 cases) and 04's threshold saves write real rows;
  thresholds self-restore, but ingestion rows persist: 1 env + 1 occupancy +
  up to 1 zero-egg production log per accepted case, plus `egg_over_hen` /
  `occupancy_mismatch` alerts. Adapt the terminal cleanup in
  `e2e/table-ix/README.md` to the run date (replace the Jan-15 predicates
  with the benchmark date) and re-run it afterwards.
- Collection 3 never dispatches a real generation job by construction
  (unknown-cage rejection); keep it that way.
