# LayRate — JSON API Contract (v1)

Short, authoritative contract for **HTTP JSON endpoints** served by the Laravel app.
SSE streams (`/environment/relay-stream` etc.) are event-streams — out of scope for
this envelope; they follow their own event format.

> The mobile app's backend (`mobile-api/app.py`, Flask) reads MySQL directly and is a
> separate lifecycle — this contract governs Laravel-served JSON only.

## 1. Success shape
```
{ "success": true, "data": <payload> }
```
- `data` may be an object, array, or `null`. No other top-level keys on success.
- **207 Partial** (sensor ingestion only): `{ "success": true, "data": { ...accepted metadata + "errors": [ ...per-reading messages ] } }` with HTTP 207 — reserved for batch endpoints that partly succeed. Documented here; nothing else may use 2xx-with-errors.

## 2. Error shape
```
{ "success": false, "error": { "message": "<human>", "errors": { "<field>": [...] } } }
```
- `message` — always present, human-readable.
- `errors` — present only for validation (422) / field-level problems; map of field → list of messages.
- Clients: branch on `success` only; use `error.message` for display, `error.errors` for field mapping.

## 3. HTTP status conventions
| Status | Meaning |
|---|---|
| 200 | OK (success envelope) |
| 201 | Created (rare; new top-level resources) |
| 207 | Partial success (batch ingestion only) |
| 400 | Malformed request / bad params |
| 403 | Forbidden (auth admin gates) |
| 404 | Not found — **always JSON**: `{success:false, error:{message:"..."}}`, never raw text |
| 422 | Validation OR business-rule failures (use `error.errors` when available) |
| 500 | Server fault — real detail only when `APP_DEBUG=true` |

Rules:
- Never 500 for user/validation-class failures — route those to 422/400/404.
- 404s must use the JSON error shape for every JSON-capable route (incl. SSE-adjacent GETs); SSE streams signal errors via `event: error`.

## 4. Web vs JSON
Controllers that serve both keep the same helper usage only in their JSON branch
(`$request->expectsJson()`); HTML/web flows (redirects, back(), session flash) are unchanged.

## 5. Envelope migration notes (for the conversion pass)
- `serial-bridge/bridge.py` parses **top-level** keys of `sensor-readings` (`message`/`accepted`/`processed`/`errors`) and `relay/command` (`relay`). Wrapping these in `data` **requires a coordinated bridge.py update in the same change** — do not convert them without it.
- The Flask mobile backend does not consume Laravel JSON (direct DB) — no consumer impact there.

## 6. Open items (this pass)
- **Alert API (apiIndex/apiMarkRead)** converted in this pass (success envelope, 207 exempt).
- **SensorIngestion / relay-command**: contract designed, **deferred** to a coordinated follow-up — `serial-bridge/bridge.py` parses **top-level** keys (`message/accepted/processed/errors`, `relay`); must change in the **same** change/deploy as the Laravel conversion once the Pi/runner is reliably reachable.
- **Model-binding 404s** (e.g. PUT `/api/alerts/{alert}/read` with a bogus id) currently return the **Laravel-default** `{"message": ...}` 404, not `{success:false,error}`. Converting requires an exception-handler mapper for `ModelNotFoundException` on JSON requests — proposed follow-up in the API-conversion pass.
