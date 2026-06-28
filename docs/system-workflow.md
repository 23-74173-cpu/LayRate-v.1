# LayRate System Workflow

Step-by-step guide on how to use the LayRate Poultry Farm Management System.

**Access:** `http://localhost/LayRate/public` (or your deployed Raspberry Pi URL)

---

## 1. Login

1. Open the system URL in a browser.
2. You are redirected to the **Login** page if not yet signed in.
3. Enter your email and password.
4. Click **Login**.
5. You land on the **Dashboard**.

Roles:
- **Admin** — full access, including deleting records (cages, egg logs, mortality logs).
- **Operator** — can view and record data, but cannot delete.

---

## 2. Dashboard (`/`)

The dashboard is the home screen after login. It shows:
- Key metrics (today's egg count, average HDEP, feed consumed, mortality today)
- Cage overview cards
- **Recent Alerts** card — environmental alerts (temperature/humidity out of threshold), unread ones shown first
- You can mark individual alerts as read, or use **Mark all as read**

This is the starting point to check the farm's overall status at a glance.

---

## 3. Cage Management (`/cages`)

1. Go to **Cages** in the sidebar.
2. View existing cages in a slot grid (CAGE-A, CAGE-B, CAGE-C, CAGE-D).
3. To add a cage: click **Add Cage**, fill in cage code, location, capacity, then **Save**.
4. To edit: click a cage card, update details, **Save**.
5. To delete (admin only): click delete on a cage card, confirm.

Do this first when setting up a new farm, since other modules (egg logging, feed, environment, mortality) all reference a cage.

---

## 4. Egg Logging (`/egg-logging`)

1. Go to **Egg Logging**.
2. Fill out the form: select cage, date, egg count, hen count.
3. Click **Save** — the system automatically computes **HDEP** (Hen-Day Egg Production = eggs ÷ hens × 100).
4. The recent logs table below updates immediately.
5. To remove a wrong entry (admin only): click delete on that row.

Log this daily per cage for accurate production tracking.

---

## 5. Environment Monitor (`/environment`)

1. Go to **Environment**.
2. View live sensor cards per cage (temperature, humidity) — fed by the Arduino sensors connected to the Raspberry Pi.
3. Status badges show **Normal**, **Watch**, or **Alert** based on configured thresholds.
4. Charts show temperature/humidity trend over time.
5. To adjust alert thresholds: scroll to **Thresholds**, enter min/max temperature and humidity, click **Save Thresholds**. These are stored in the database and immediately affect alert status everywhere (dashboard, reports).

When a reading crosses a threshold, an alert is automatically created and shows up on the Dashboard's Recent Alerts card.

---

## 6. Feed & Nutrition (`/feed`)

1. Go to **Feed & Nutrition**.
2. **Feed Batches** — add a new batch: batch code, crude protein %, notes, then **Save**. Edit existing batches if needed.
3. **Consumption Logs** — record daily feed consumption: select cage, date, batch used, kg consumed, then **Save**.

Feed batches must exist before logging consumption against them.

---

## 7. Mortality Log (`/mortality`)

1. Go to **Mortality Log**.
2. Fill out the form: cage, date, number of deaths, reason (Disease, Heat Stress, Injury, Predator, Unknown, Other), optional notes.
3. Click **Save**.
4. To remove a wrong entry (admin only): click delete on that row.

This data feeds the Mortality Report and dashboard's "mortality today" metric.

---

## 8. Analytics (`/analytics`)

1. Go to **Analytics**.
2. View charts: HDEP trend over time, eggs produced per cage, feed vs. production scatter plot.
3. Use this to spot patterns — e.g. declining HDEP, feed inefficiency, or seasonal trends.

Read-only — no data entry here.

---

## 9. Forecast (`/forecast`)

1. Go to **Forecast**.
2. Click **Generate Forecast** to project future egg production based on historical data.
3. View the forecast chart compared against actual historical data.

Useful for planning feed orders and projecting output.

---

## 10. Reports (`/reports`)

1. Go to **Reports**.
2. Select **Report Type** (Production, Feed, Environment, or Mortality).
3. Select **From** and **To** dates, and optionally filter by **Cage** (and **Reason** for mortality reports).
4. Click **Generate Report** — a formal printable document appears with letterhead, summary stats, and a data table.
5. Click **Export CSV** to download the raw data, or click the **Print** icon to print/save as PDF.

This is the report you hand to your adviser/panel or print for farm records — it includes a "Prepared by / Noted by" signature block.

---

## Typical Daily Routine

```
Morning  → Check Dashboard (alerts, overnight environment readings)
         → Log eggs for each cage (Egg Logging)
         
         → Log feed consumption (Feed & Nutrition)

Anytime  → Log mortality if it occurs (Mortality Log)
         → Check Environment Monitor if an alert fires

Weekly   → Review Analytics for trends
         → Generate Forecast for planning
         → Generate Reports for records/submission
```

---

## Logout

Click the logout icon (top-right of the header) to end your session securely.
