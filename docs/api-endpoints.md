# LayRate API Endpoints

**Case:** Own backend — Laravel 12 monolith with one JSON REST API for sensor ingestion.

---

## Feature-by-Feature Endpoint Inventory

### Authentication

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/login` | GET | Login page | — |
| `/login` | POST | Verify credentials | Sends: `email, password` → Sets session |
| `/logout` | POST | End session | Destroys auth session |

### Dashboard

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/` | GET | Farm overview — HDEP, totals, alerts, feed progress | Returns Blade view with aggregated stats |
| `/dashboard/stats` | GET | Stats for live refresh | Returns JSON: `total_hens, today_eggs, avg_hdep` |
| `/dashboard/cage-overview` | GET | Per-cage summary cards | Returns Blade partial |
| `/dashboard/feed-mortality` | GET | Today's feed + mortality per cage | Returns Blade partial |

### Cage Management

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/cages` | GET | Slot-grid UI showing all cages | Blade view with cage + slot data |
| `/cages` | POST | Create cage with battery-cage config | Sends: `rows, slots_per_row, max_chickens_per_slot` |
| `/cages/{cage}` | PUT | Update cage dimensions (resize-safe) | Validates no orphaned hens/sensors |
| `/cages/{cage}` | DELETE | Delete cage + related records | Admin-only; data-loss confirmation first |
| `/cages/{cage}/slots-json` | GET | Slot data for AJAX popovers | JSON: occupancy, sensor status per slot |
| `/cages/slots/{slot}/hens-json` | GET | Hen details for a slot | JSON: hen IDs, breeds, ages |
| `/cages/{cage}/sensor-info` | GET | Sensor assignment info per cage | JSON: hardware items, types |
| `/cages/{cage}/delete-info` | GET | Data-loss counts before delete | JSON: related record counts |
| `/cages/{cage}/confirm-delete` | GET | Delete confirmation page | Blade view with data-loss table |
| `/cages/{cage}/force` | DELETE | Force-delete cage | Cascading delete of all related |
| `/cages/{cage}/position` | PATCH | Update cage grid position | Sends: `grid_row, grid_col` |
| `/cages/batch-position` | POST | Batch update all cage positions | Sends: array of `{id, row, col}` |
| `/cages/remove-cell` | POST | Remove grid cell placeholder | Sends: `row, col` |
| `/cages/{cage}/slots/reorder` | POST | Reorder slot numbers | Sends: array of slot IDs in new order |
| `/cages/bulk-add` | GET/POST | Bulk-create cages | Sends: array of cage configs |
| `/cages/{cage}/print-label` | GET | Print cage label | Returns printable view |

### Egg Logging

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/eggs/logging` | GET | Egg entry UI per cage slot | Blade with slots + today's logs |
| `/eggs/logging` | POST | Record daily production log | Sends: `cage_slot_id, log_date, egg_count, hen_count, size_*` |
| `/eggs/logging/{productionLog}` | PUT | Edit production log | Same validation as store |
| `/eggs/logging/{productionLog}` | DELETE | Admin-delete a log | Admin middleware |
| `/eggs/logging/logs` | GET | Paginated filtered log table | Filters: cage, slot, breed, logged_via |
| `/eggs/recent-logs` | GET | Recent logs page with filters | Blade view with paginated table |
| `/eggs/logging/verify-override` | POST | Sensor-lock override via PIN/password | Sends: `cage_slot_id, pin` (rate-limited 6/min) |

### Egg Stocks (Inventory)

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/eggs/stocks` | GET | Egg batch inventory UI | Blade view |
| `/eggs/stocks` | POST | Create egg grade batch | Sends: `grade, quantity, weight_g, date_produced` |
| `/eggs/stocks/{batch}` | PUT | Update batch | Sends: same fields as store |
| `/eggs/stocks/{batch}` | DELETE | Delete batch | Soft-delete |
| `/eggs/stocks/live-data` | GET | AJAX inventory table | Blade partial |
| `/eggs/stocks/{batch}/qr` | GET | QR code for batch traceability | Returns QR image |

### Pre-Orders

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/eggs/pre-orders` | GET | Pre-order management UI | Blade view |
| `/eggs/pre-orders` | POST | Create customer order | Sends: `customer, quantity, due_date, status` |
| `/eggs/pre-orders/{order}` | PATCH | Update order status | Sends: status transition |
| `/eggs/pre-orders/{order}` | DELETE | Cancel order | Soft-delete |
| `/eggs/pre-orders/table` | GET | AJAX orders table | Blade partial |

### Environment Monitoring

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/environment` | GET | Sensor cards + thresholds config | Blade view |
| `/environment/live-data` | GET | Latest readings + 24h trends | Blade partial with Chart.js data |
| `/environment/logs` | GET | Recent env log summary | Blade partial |
| `/environment/thresholds` | POST | Save alert thresholds | Sends: `temp_min, temp_max, hum_min, hum_max` |
| `/environment/egg-weights` | POST | Save egg weight config per grade | Sends: per-grade gram weights |

### Feed & Nutrition

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/feed` | GET | Dual-tab UI: batches + consumption | Blade view |
| `/feed/live-data` | GET | Batches, consumption table, FCR stats | Blade partial |
| `/feed/batch` | POST | Add feed batch | Sends: `brand, crude_protein, total_quantity_kg, unit_cost` |
| `/feed/batch/{feedBatch}` | PUT | Update batch | Same fields |
| `/feed/batch/{feedBatch}` | DELETE | Delete batch (if unused) | Checks for consumption refs |
| `/feed/batch/{feedBatch}/delete-check` | GET | Check if batch can be deleted | JSON: `{can_delete, count}` |
| `/feed/consumption` | POST | Log daily feed per cage | Sends: `cage_id, feed_batch_id, log_date, feed_consumed_kg` |
| `/feed/consumption/{log}` | PUT | Edit consumption | Same fields |
| `/feed/consumption/{log}` | DELETE | Delete consumption | Source must be 'direct' |
| `/feed/farm-entry` | POST | Whole-farm feeding (auto-distributes) | Sends: `feed_batch_id, log_date, total_kg` |
| `/feed/farm-entry/{entry}` | PUT | Edit farm entry + redistribute | Same fields |
| `/feed/farm-entry/{entry}` | DELETE | Delete farm entry | Cascades to consumption logs |

### Mortality

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/mortality` | GET | Mortality recording UI | Blade view |
| `/mortality` | POST | Record deaths | Sends: `cage_id, log_date, count, reason, notes` |
| `/mortality/{mortalityLog}` | PUT | Edit mortality (adjust count ±) | Reactivates/deactivates hens |
| `/mortality/{mortalityLog}` | DELETE | Admin-delete + restore hens | Reverses all effects in transaction |
| `/mortality/logs` | GET | Paginated mortality table | Blade partial |

### Chickens / Inventory

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/chickens` | GET | Hen inventory & placement UI | Blade view |
| `/chickens` | POST | Add chickens to slot | Sends: `cage_slot_id, breed, tag_code, flock_age_weeks` |
| `/chickens/inventory-list` | GET | Filterable hen table | Blade partial |
| `/chickens/move` | POST | Move hen between slots | Sends: `hen_id, to_slot_id` |
| `/chickens/remove` | POST | Remove hen from inventory | Sends: `hen_id` |
| `/chickens/health-event` | POST | Log health issue | Sends: `hen_id, event_type, notes` |
| `/chickens/weight-check` | POST | Log weight reading | Sends: `hen_id, weight_kg` |
| `/chickens/cull` | POST | Culling record | Sends: `hen_id, reason, notes` |
| `/chickens/removal` | POST | Non-mortality removal | Sends: `hen_id, reason` |
| `/chickens/mortality-records` | GET | Mortality history per hen | Blade partial |
| `/chickens/culling-records` | GET | Culling history | Blade partial |
| `/chickens/removal-records` | GET | Removal history | Blade partial |

### Analytics

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/analytics` | GET | HDEP trends, eggs bar, feed-vs-HDEP scatter | Filters: `cage, period (week/month/3months)` |
| `/analytics/charts` | GET | Same data as Blade partial (AJAX) | Chart partial for live tab switching |

### Forecasting

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/forecast` | GET | Forecast UI per cage or whole-farm | Blade view |
| `/forecast/generate` | POST | Generate + persist forecast | 14-day avg + deterministic variation |
| `/forecast/template` | GET | Download import template CSV | CSV download |
| `/forecast/import` | POST | Import forecast data from CSV | Sends: uploaded CSV file |

### Reports

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/reports` | GET | Report form + printable document | Filters: `type, from, to, cage, reason` |
| `/reports/csv` | GET | Stream CSV export | Same filters → `Content-Type: text/csv` |

### Account Settings

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/account` | GET | Password/PIN settings page | Blade view |
| `/account/password` | POST | Change password | Sends: `current_password, new_password` |
| `/account/pin` | POST | Set/change override PIN (4-6 digits) | Rejects 14 weak PINs |

### Alerts / Notifications

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/notifications` | GET | Notification center | Blade view |
| `/notifications/table` | GET | Paginated alert table | Blade partial |
| `/alerts/acknowledge-modal` | POST | Modal to acknowledge selected alerts | Sends: array of alert IDs |
| `/alerts/{alert}/read` | POST | Mark single alert read | Toggles `is_read` |
| `/alerts/read-all` | POST | Mark all alerts read | Bulk update |

### Hardware (Sensor Registry)

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/hardware` | GET | Hardware inventory UI | Blade view |
| `/hardware` | POST | Register a sensor | Sends: `serial_number, device_type, cage_id, cage_slot_id` |
| `/hardware/{hardwareItem}` | PUT | Update hardware assignment | Same fields |
| `/hardware/{hardwareItem}` | DELETE | Deregister hardware | Soft-delete |
| `/hardware/live-data` | GET | Hardware table (AJAX) | Blade partial |

### Devices (API Keys)

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/devices` | POST | Register LAN device (API key) | Generates pre-shared key |
| `/devices/{device}/regenerate-key` | POST | Rotate API key | Admin only |
| `/devices/{device}` | DELETE | Remove device | Admin only |

### Sensor Ingestion (JSON REST API)

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/api/sensor-readings` | POST | Ingest DHT22 + IR breakbeam data | **Sends:** `{readings: [{serial_number, temperature_c/humidity_pct or count}], recorded_at}` + `X-Device-Key` header<br>**Returns:** JSON `{message, accepted, processed, errors}` |

### Notes

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/notes` | GET | Operator notes list | Blade view |
| `/notes` | POST | Create note | Sends: `content, cage_id` |
| `/notes/{note}` | PUT | Update note | Same fields |
| `/notes/{note}` | DELETE | Delete note | — |

### Settings

| Endpoint | Method | Purpose | Data |
|---|---|---|---|
| `/settings/farm-layout` | POST | Save grid dimensions | Sends: `farm_grid_rows, farm_grid_cols` |
