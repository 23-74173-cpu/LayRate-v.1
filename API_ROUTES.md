# LayRate — API Route List

**Project:** LayRate — Offline Poultry Farm Management System
**Framework:** Laravel 12 (PHP)
**Author:** FelmanE30
**Date:** 2026-07-08

---

## Overview

LayRate is a web-based system for managing a poultry farm. It tracks cages, chickens, egg production, feed consumption, mortality, and environmental conditions.

All routes follow the **REST** convention and are protected by session-based authentication. Routes marked **[Admin Only]** require an administrator account.

---

## Authentication Routes

These routes handle user login and logout. They do **not** require the user to be logged in.

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/login` | Show the login page |
| `POST` | `/login` | Submit login credentials |
| `POST` | `/logout` | Log out the current user |

---

## Dashboard

The main overview page shown after logging in. Displays a summary of total hens, egg production, feed, mortality, and live sensor readings.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/` | `dashboard` | Show the farm dashboard |

---

## Cages

Manages the physical cages in the farm. Each cage contains a grid of slots that hold hens.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/cages` | `cages.index` | List all cages |
| `POST` | `/cages` | `cages.store` | Add a new cage |
| `PUT` | `/cages/{cage}` | `cages.update` | Edit an existing cage |
| `DELETE` | `/cages/{cage}` | `cages.destroy` | Delete a cage **[Admin Only]** |
| `GET` | `/cages/{cage}/bulk-add` | `bulk-add.show` | Show the bulk hen import form for a cage |
| `POST` | `/cages/{cage}/bulk-add` | `bulk-add.store` | Submit bulk hen import for a cage |

> `{cage}` is the ID of the cage being acted on.

---

## Egg Logging

Records daily egg production per cage slot. Supports override verification for corrections.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/egg-logging` | `egg-logging` | Show the egg logging page |
| `POST` | `/egg-logging` | `egg-logging.store` | Submit a new egg log entry |
| `POST` | `/egg-logging/verify-override` | `egg-logging.verify-override` | Request a PIN to override an existing log (rate-limited: 6 attempts/min) |
| `DELETE` | `/egg-logging/{productionLog}` | `egg-logging.destroy` | Delete an egg log entry **[Admin Only]** |

---

## Environment Monitoring

Tracks temperature and humidity readings from sensors installed in cages.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/environment` | `environment` | Show environment monitoring page |
| `GET` | `/environment/live-data` | `environment.live-data` | Fetch real-time sensor readings (JSON) |
| `GET` | `/environment/logs` | `environment.logs` | View historical environmental logs |
| `POST` | `/environment/thresholds` | `environment.thresholds` | Save alert threshold settings |

---

## Feed Management

Records feed inventory (batches) and daily feed consumption per cage.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/feed` | `feed` | Show feed management page |
| `POST` | `/feed/batch` | `feed.batch.store` | Add a new feed batch |
| `PUT` | `/feed/batch/{feedBatch}` | `feed.batch.update` | Edit a feed batch |
| `POST` | `/feed/consumption` | `feed.consumption.store` | Log daily feed consumption for a cage |

---

## Analytics

Provides visual charts and summaries of egg production trends, HDEP (Hen Day Egg Production) rates, and cage performance.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/analytics` | `analytics` | Show the analytics overview page |
| `GET` | `/analytics/charts` | `analytics.charts` | Return chart data for the analytics page |

---

## Forecast

Uses historical egg production data to generate predicted future egg counts per cage.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/forecast` | `forecast` | Show the forecast page |
| `POST` | `/forecast/generate` | `forecast.generate` | Run the forecast model and save results |
| `GET` | `/forecast/results` | `forecast.results` | View the most recent forecast results |

---

## Mortality Logging

Records daily hen deaths per cage, grouped by cause.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/mortality` | `mortality.index` | Show the mortality log page |
| `GET` | `/mortality/logs` | `mortality.logs` | View all mortality records |
| `POST` | `/mortality` | `mortality.store` | Submit a new mortality entry |
| `DELETE` | `/mortality/{mortalityLog}` | `mortality.destroy` | Delete a mortality record **[Admin Only]** |

---

## Reports

Generates summary reports of egg production and other farm data, with CSV export.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/reports` | `reports` | Show the reports page |
| `GET` | `/reports/csv` | `reports.csv` | Download the report as a CSV file |

---

## Account Settings

Allows the logged-in user to update their password and security PIN.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `GET` | `/account` | `account` | Show account settings page |
| `POST` | `/account/password` | `account.password` | Update account password |
| `POST` | `/account/pin` | `account.pin` | Update security PIN |

---

## Alerts / Notifications

System alerts are generated automatically (e.g. temperature out of range). These routes allow users to acknowledge or dismiss them.

| Method | URL | Route Name | Purpose |
|--------|-----|-----------|---------|
| `POST` | `/alerts/{alert}/read` | `alerts.read` | Mark a single alert as read |
| `POST` | `/alerts/read-all` | `alerts.read-all` | Mark all alerts as read |

---

## HTTP Methods Explained

| Method | Meaning |
|--------|---------|
| `GET` | Retrieve / display a page or data |
| `POST` | Submit new data (create) |
| `PUT` | Update/replace existing data |
| `DELETE` | Remove existing data |

---

## Access Control Summary

| Access Level | Description |
|---|---|
| **Guest** | Can only access `/login` |
| **Authenticated** | Can access all other routes after logging in |
| **Admin Only** | Restricted actions: deleting cages, egg logs, and mortality records |

---

*Total routes: 42 — Framework: Laravel 12 — Auth: Session-based middleware*
