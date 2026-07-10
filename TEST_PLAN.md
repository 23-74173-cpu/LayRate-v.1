# LayRate — Test Plan

**Project:** LayRate — Offline Poultry Farm Management System
**Framework:** Laravel 12 (PHP), MySQL, Blade + Tailwind CSS
**Date:** 2026-07-08
**Scope:** Functional testing of all active modules
**Excluded:** Forecast, Analytics, Reports *(deferred to next testing cycle)*

---

## Testers

| Tester | Assigned Modules |
|--------|-----------------|
| **Tester 1** | Authentication, Dashboard, Cage Management, Bulk Hen Import |
| **Tester 2** | Egg Logging, Feed Management, Mortality Logging, Environment Monitoring, Alerts, Account Settings |

---

## Test Environment

- **URL:** `http://localhost/LayRate/public`
- **Server:** XAMPP (Apache + MySQL)
- **Browser:** Google Chrome (latest)
- **Test Account:** Use the seeded admin account

---

## How to Record Results

For each test case, mark the result as:
- ✅ **PASS** — behaves exactly as expected
- ❌ **FAIL** — does not behave as expected (note what happened)
- ⚠️ **PARTIAL** — works but with issues (note what is missing)

---

---

# TESTER 1 — Authentication, Dashboard, Cages

---

## Module 1: Authentication

**Purpose:** Ensures only authorized users can access the system.

---

### TC-AUTH-01 — Show Login Page

| Field | Detail |
|-------|--------|
| **Precondition** | User is not logged in |
| **Steps** | 1. Open `http://localhost/LayRate/public/login` |
| **Expected** | Login form is displayed with Email, Password fields and Sign In button |
| **Result** | |
| **Notes** | |

---

### TC-AUTH-02 — Login with Valid Credentials

| Field | Detail |
|-------|--------|
| **Precondition** | User has a valid account |
| **Steps** | 1. Enter correct email and password → 2. Click Sign In |
| **Expected** | Redirected to Dashboard (`/`) |
| **Result** | |
| **Notes** | |

---

### TC-AUTH-03 — Login with Wrong Password

| Field | Detail |
|-------|--------|
| **Precondition** | User has a valid account |
| **Steps** | 1. Enter correct email but wrong password → 2. Click Sign In |
| **Expected** | Stays on login page, shows "Invalid email or password" error |
| **Result** | |
| **Notes** | |

---

### TC-AUTH-04 — Login with Empty Fields

| Field | Detail |
|-------|--------|
| **Precondition** | None |
| **Steps** | 1. Leave email and password blank → 2. Click Sign In |
| **Expected** | Form validation prevents submission, fields are highlighted |
| **Result** | |
| **Notes** | |

---

### TC-AUTH-05 — Access Protected Page Without Login

| Field | Detail |
|-------|--------|
| **Precondition** | User is not logged in |
| **Steps** | 1. Type `http://localhost/LayRate/public/` directly in browser |
| **Expected** | Redirected to `/login` |
| **Result** | |
| **Notes** | |

---

### TC-AUTH-06 — Logout

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Click the logout button |
| **Expected** | Session is cleared, redirected to login page |
| **Result** | |
| **Notes** | |

---

## Module 2: Dashboard

**Purpose:** Displays a real-time overview of the entire farm — total hens, egg production, feed, mortality, and environmental readings per cage.

---

### TC-DASH-01 — Dashboard Loads Successfully

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/` |
| **Expected** | Dashboard page loads with metric cards (Total Hens, Eggs Today, HDEP, Feed, Mortality) |
| **Result** | |
| **Notes** | |

---

### TC-DASH-02 — Total Hen Count is Correct

| Field | Detail |
|-------|--------|
| **Precondition** | Hens have been added to cage slots |
| **Steps** | 1. Check the "Total Hens" card on the dashboard |
| **Expected** | Matches the sum of `current_occupancy` across all cage slots |
| **Result** | |
| **Notes** | |

---

### TC-DASH-03 — Eggs Today Updates After Logging

| Field | Detail |
|-------|--------|
| **Precondition** | User has logged eggs for today |
| **Steps** | 1. Log egg count via Egg Logging → 2. Return to Dashboard |
| **Expected** | "Eggs Today" card shows the updated total |
| **Result** | |
| **Notes** | |

---

### TC-DASH-04 — Cage Overview Cards Display

| Field | Detail |
|-------|--------|
| **Precondition** | At least one cage exists |
| **Steps** | 1. Scroll to the Cage Overview section on Dashboard |
| **Expected** | Each cage shows its cage code, occupancy, and sensor count |
| **Result** | |
| **Notes** | |

---

### TC-DASH-05 — Alert Count Badge

| Field | Detail |
|-------|--------|
| **Precondition** | Unread alerts exist |
| **Steps** | 1. Check the alerts section on the Dashboard |
| **Expected** | Shows the correct number of unread alerts |
| **Result** | |
| **Notes** | |

---

## Module 3: Cage Management

**Purpose:** Manages physical cages in the farm. Each cage is divided into a grid of slots that hold hens. Admins can add, edit, and delete cages.

---

### TC-CAGE-01 — List All Cages

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/cages` |
| **Expected** | Page displays all existing cages with their cage codes, locations, and slot grid |
| **Result** | |
| **Notes** | |

---

### TC-CAGE-02 — Add a New Cage

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Click "Add Cage" → 2. Fill in Cage Code, Rows, Slots per Row, Max Chickens per Slot → 3. Submit |
| **Expected** | New cage appears in the cage list with correct details |
| **Result** | |
| **Notes** | |

---

### TC-CAGE-03 — Add Cage with Duplicate Code

| Field | Detail |
|-------|--------|
| **Precondition** | CAGE-A already exists |
| **Steps** | 1. Try to add a new cage with code "CAGE-A" |
| **Expected** | Validation error: cage code must be unique |
| **Result** | |
| **Notes** | |

---

### TC-CAGE-04 — Edit an Existing Cage

| Field | Detail |
|-------|--------|
| **Precondition** | At least one cage exists |
| **Steps** | 1. Click Edit on a cage → 2. Change the location → 3. Save |
| **Expected** | Changes are saved and reflected in the cage list |
| **Result** | |
| **Notes** | |

---

### TC-CAGE-05 — Delete a Cage (Admin Only)

| Field | Detail |
|-------|--------|
| **Precondition** | Admin account is logged in, a cage with no hens exists |
| **Steps** | 1. Click Delete on an empty cage → 2. Confirm deletion |
| **Expected** | Cage is removed from the list |
| **Result** | |
| **Notes** | |

---

### TC-CAGE-06 — Non-Admin Cannot Delete Cage

| Field | Detail |
|-------|--------|
| **Precondition** | Non-admin account is logged in |
| **Steps** | 1. Navigate to `/cages` and look for Delete button |
| **Expected** | Delete button is not visible or is disabled |
| **Result** | |
| **Notes** | |

---

### TC-CAGE-07 — Slot Grid Displays Correctly

| Field | Detail |
|-------|--------|
| **Precondition** | A cage with multiple rows and slots exists |
| **Steps** | 1. View the cage's slot grid on `/cages` |
| **Expected** | Slots are displayed in a grid matching the cage's rows × slots_per_row configuration |
| **Result** | |
| **Notes** | |

---

## Module 4: Bulk Hen Import

**Purpose:** Allows multiple hens to be added to a specific cage slot in one submission, instead of one at a time.

---

### TC-BULK-01 — Open Bulk Add Form

| Field | Detail |
|-------|--------|
| **Precondition** | A cage exists with available slots |
| **Steps** | 1. Navigate to `/cages/{cage}/bulk-add` |
| **Expected** | Bulk add form loads showing available slots |
| **Result** | |
| **Notes** | |

---

### TC-BULK-02 — Submit Valid Bulk Hen Import

| Field | Detail |
|-------|--------|
| **Precondition** | A cage has empty slots |
| **Steps** | 1. Select a slot → 2. Fill in breed, quantity, date acquired → 3. Submit |
| **Expected** | Hens are added; slot's `current_occupancy` increases accordingly |
| **Result** | |
| **Notes** | |

---

### TC-BULK-03 — Exceed Max Chickens Per Slot

| Field | Detail |
|-------|--------|
| **Precondition** | A slot has a max of e.g. 5 chickens |
| **Steps** | 1. Try to add more hens than the slot's maximum allows |
| **Expected** | Validation error shown, import is rejected |
| **Result** | |
| **Notes** | |

---

### TC-BULK-04 — Invalid Breed is Rejected

| Field | Detail |
|-------|--------|
| **Precondition** | None |
| **Steps** | 1. Enter a breed name not in the allowed list (e.g. "Unknown Breed") |
| **Expected** | Validation error: breed must be one of the accepted values |
| **Result** | |
| **Notes** | Accepted breeds: ISA Brown, Lohmann Brown-Classic, Dekalb White, Hy-Line Brown, Novogen Brown |

---

---

# TESTER 2 — Egg Logging, Feed, Mortality, Environment, Alerts, Account

---

## Module 5: Egg Logging

**Purpose:** Records daily egg production per cage slot. Tracks egg count, Hen Day Egg Production (HDEP) rate, and supports PIN-protected overrides for corrections.

---

### TC-EGG-01 — View Egg Logging Page

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in, cages with hens exist |
| **Steps** | 1. Navigate to `/egg-logging` |
| **Expected** | Page shows all active cage slots with today's egg count |
| **Result** | |
| **Notes** | |

---

### TC-EGG-02 — Log Eggs for a Slot

| Field | Detail |
|-------|--------|
| **Precondition** | A cage slot has active hens |
| **Steps** | 1. Enter egg count for a slot → 2. Submit |
| **Expected** | Log is saved; slot shows updated egg count for today |
| **Result** | |
| **Notes** | |

---

### TC-EGG-03 — HDEP is Calculated Correctly

| Field | Detail |
|-------|--------|
| **Precondition** | Egg log submitted for a slot |
| **Steps** | 1. Log eggs → 2. Check HDEP displayed |
| **Expected** | HDEP = (egg_count / hen_count) × 100, shown as a percentage |
| **Result** | |
| **Notes** | |

---

### TC-EGG-04 — Log 0 Eggs

| Field | Detail |
|-------|--------|
| **Precondition** | A slot has active hens |
| **Steps** | 1. Enter 0 as the egg count and submit |
| **Expected** | Log is saved with 0 eggs; HDEP shows 0% |
| **Result** | |
| **Notes** | |

---

### TC-EGG-05 — Override Existing Log (PIN Verification)

| Field | Detail |
|-------|--------|
| **Precondition** | An egg log already exists for today |
| **Steps** | 1. Enter a different egg count → 2. Submit → 3. Enter PIN when prompted |
| **Expected** | PIN is verified, log is updated with new count |
| **Result** | |
| **Notes** | |

---

### TC-EGG-06 — Override Rejected with Wrong PIN

| Field | Detail |
|-------|--------|
| **Precondition** | An egg log already exists for today |
| **Steps** | 1. Attempt override → 2. Enter incorrect PIN |
| **Expected** | Override is rejected; original log unchanged |
| **Result** | |
| **Notes** | |

---

### TC-EGG-07 — Delete Egg Log (Admin Only)

| Field | Detail |
|-------|--------|
| **Precondition** | Admin is logged in, an egg log exists |
| **Steps** | 1. Find the log in the list → 2. Click Delete → 3. Confirm |
| **Expected** | Log is removed from the system |
| **Result** | |
| **Notes** | |

---

### TC-EGG-08 — Filter Logs by Cage

| Field | Detail |
|-------|--------|
| **Precondition** | Multiple cages with logs exist |
| **Steps** | 1. On `/egg-logging`, select a specific cage from the filter |
| **Expected** | Only slots belonging to that cage are shown |
| **Result** | |
| **Notes** | |

---

## Module 6: Feed Management

**Purpose:** Tracks feed inventory (batches) and records daily feed consumption per cage. Warns when a feed batch is running low.

---

### TC-FEED-01 — View Feed Page

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/feed` |
| **Expected** | Page shows existing feed batches with remaining stock |
| **Result** | |
| **Notes** | |

---

### TC-FEED-02 — Add a Feed Batch

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Click "Add Batch" → 2. Fill in brand, quantity (kg), unit cost, crude protein, date received → 3. Submit |
| **Expected** | New batch appears in the list with auto-generated batch code (e.g. F-2026-001) |
| **Result** | |
| **Notes** | |

---

### TC-FEED-03 — Edit a Feed Batch

| Field | Detail |
|-------|--------|
| **Precondition** | A feed batch exists |
| **Steps** | 1. Click Edit on a batch → 2. Change the quantity or notes → 3. Save |
| **Expected** | Updated values are saved and reflected |
| **Result** | |
| **Notes** | |

---

### TC-FEED-04 — Log Feed Consumption

| Field | Detail |
|-------|--------|
| **Precondition** | A feed batch with remaining stock exists |
| **Steps** | 1. Select a cage and feed batch → 2. Enter kg consumed → 3. Submit |
| **Expected** | Consumption is logged; remaining stock of the batch decreases |
| **Result** | |
| **Notes** | |

---

### TC-FEED-05 — Low Stock Warning

| Field | Detail |
|-------|--------|
| **Precondition** | A feed batch has remaining stock at or below its threshold |
| **Steps** | 1. View the feed page |
| **Expected** | Low-stock batch is visually highlighted or flagged |
| **Result** | |
| **Notes** | |

---

### TC-FEED-06 — Crude Protein Color Indicator

| Field | Detail |
|-------|--------|
| **Precondition** | Feed batches with varying crude protein levels exist |
| **Steps** | 1. View the feed list |
| **Expected** | Batches with CP ≥ 17.5% show green, 16.5–17.4% show yellow, below 16.5% show red |
| **Result** | |
| **Notes** | |

---

## Module 7: Mortality Logging

**Purpose:** Records daily hen deaths per cage, with a reason (Disease, Heat Stress, Injury, etc.). Helps track flock health trends.

---

### TC-MORT-01 — View Mortality Page

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/mortality` |
| **Expected** | Page loads with mortality log form and existing records |
| **Result** | |
| **Notes** | |

---

### TC-MORT-02 — Log a Mortality Entry

| Field | Detail |
|-------|--------|
| **Precondition** | At least one cage with hens exists |
| **Steps** | 1. Select cage → 2. Enter count, reason, date → 3. Submit |
| **Expected** | Mortality record saved and appears in the log list |
| **Result** | |
| **Notes** | |

---

### TC-MORT-03 — Log with All Reason Options

| Field | Detail |
|-------|--------|
| **Precondition** | None |
| **Steps** | 1. Try submitting logs with each reason: Disease, Heat Stress, Injury, Predator, Unknown, Other |
| **Expected** | All reason options are accepted |
| **Result** | |
| **Notes** | |

---

### TC-MORT-04 — Log with 0 Count is Rejected

| Field | Detail |
|-------|--------|
| **Precondition** | None |
| **Steps** | 1. Enter 0 as the hen count and submit |
| **Expected** | Validation error; entry is not saved |
| **Result** | |
| **Notes** | |

---

### TC-MORT-05 — View Mortality History

| Field | Detail |
|-------|--------|
| **Precondition** | Mortality logs exist |
| **Steps** | 1. Navigate to `/mortality/logs` |
| **Expected** | All past mortality records are listed with date, cage, count, and reason |
| **Result** | |
| **Notes** | |

---

### TC-MORT-06 — Delete Mortality Record (Admin Only)

| Field | Detail |
|-------|--------|
| **Precondition** | Admin is logged in, a mortality log exists |
| **Steps** | 1. Find the record → 2. Click Delete → 3. Confirm |
| **Expected** | Record is removed |
| **Result** | |
| **Notes** | |

---

## Module 8: Environment Monitoring

**Purpose:** Displays real-time temperature and humidity readings from sensors installed per cage. Triggers alerts when readings exceed thresholds.

---

### TC-ENV-01 — View Environment Page

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/environment` |
| **Expected** | Page loads showing latest temperature and humidity per cage |
| **Result** | |
| **Notes** | |

---

### TC-ENV-02 — Live Data Endpoint Returns Data

| Field | Detail |
|-------|--------|
| **Precondition** | Environmental logs exist |
| **Steps** | 1. Navigate to `/environment/live-data` |
| **Expected** | JSON response with latest readings per cage |
| **Result** | |
| **Notes** | |

---

### TC-ENV-03 — View Environment Logs

| Field | Detail |
|-------|--------|
| **Precondition** | Environmental logs exist |
| **Steps** | 1. Navigate to `/environment/logs` |
| **Expected** | Historical table of all temperature and humidity readings |
| **Result** | |
| **Notes** | |

---

### TC-ENV-04 — Normal Reading — No Alert

| Field | Detail |
|-------|--------|
| **Precondition** | Latest reading is within safe range (temp ≤ 28.5°C, humidity ≤ 70%) |
| **Steps** | 1. View the environment page |
| **Expected** | Status shows "Normal" / green indicator |
| **Result** | |
| **Notes** | |

---

### TC-ENV-05 — Watch Status Triggered

| Field | Detail |
|-------|--------|
| **Precondition** | Latest reading is temp > 28.5°C or humidity approaching 70% |
| **Steps** | 1. View the environment page |
| **Expected** | Status shows "Watch" / yellow indicator |
| **Result** | |
| **Notes** | |

---

### TC-ENV-06 — Alert Status Triggered

| Field | Detail |
|-------|--------|
| **Precondition** | Latest reading exceeds threshold (temp > 30°C or humidity > 70%) |
| **Steps** | 1. View the environment page |
| **Expected** | Status shows "Alert" / red indicator |
| **Result** | |
| **Notes** | |

---

### TC-ENV-07 — Save Threshold Settings

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/environment` → 2. Update threshold values → 3. Submit |
| **Expected** | New thresholds are saved successfully |
| **Result** | |
| **Notes** | |

---

## Module 9: Alerts / Notifications

**Purpose:** System-generated alerts for abnormal conditions (e.g. high temperature, low feed stock). Users can dismiss individual alerts or clear all.

---

### TC-ALERT-01 — Alert Count Shows in Dashboard

| Field | Detail |
|-------|--------|
| **Precondition** | Unread alerts exist |
| **Steps** | 1. View the Dashboard |
| **Expected** | Alert count badge shows the correct number of unread alerts |
| **Result** | |
| **Notes** | |

---

### TC-ALERT-02 — Mark Single Alert as Read

| Field | Detail |
|-------|--------|
| **Precondition** | At least one unread alert exists |
| **Steps** | 1. Click the "Mark as Read" button on a single alert |
| **Expected** | Alert is marked read; count decreases by 1 |
| **Result** | |
| **Notes** | |

---

### TC-ALERT-03 — Mark All Alerts as Read

| Field | Detail |
|-------|--------|
| **Precondition** | Multiple unread alerts exist |
| **Steps** | 1. Click "Mark All as Read" |
| **Expected** | All alerts marked read; count shows 0 |
| **Result** | |
| **Notes** | |

---

## Module 10: Account Settings

**Purpose:** Allows the logged-in user to update their password and security PIN used for log overrides.

---

### TC-ACCT-01 — View Account Page

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Navigate to `/account` |
| **Expected** | Account settings page loads showing user info and change forms |
| **Result** | |
| **Notes** | |

---

### TC-ACCT-02 — Change Password Successfully

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Enter current password → 2. Enter new password → 3. Confirm new password → 4. Submit |
| **Expected** | Password updated; success message shown |
| **Result** | |
| **Notes** | |

---

### TC-ACCT-03 — Change Password with Wrong Current Password

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Enter incorrect current password → 2. Submit |
| **Expected** | Error shown, password not changed |
| **Result** | |
| **Notes** | |

---

### TC-ACCT-04 — Change Password — Mismatch Confirmation

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Enter a new password → 2. Enter a different value in Confirm Password → 3. Submit |
| **Expected** | Validation error: passwords do not match |
| **Result** | |
| **Notes** | |

---

### TC-ACCT-05 — Update Security PIN

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Enter current PIN → 2. Enter new PIN → 3. Submit |
| **Expected** | PIN updated; success message shown |
| **Result** | |
| **Notes** | |

---

### TC-ACCT-06 — Update PIN with Wrong Current PIN

| Field | Detail |
|-------|--------|
| **Precondition** | User is logged in |
| **Steps** | 1. Enter incorrect current PIN → 2. Submit |
| **Expected** | Error shown, PIN not changed |
| **Result** | |
| **Notes** | |

---

## Summary

| Module | Test Cases | Tester |
|--------|-----------|--------|
| Authentication | TC-AUTH-01 to TC-AUTH-06 | Tester 1 |
| Dashboard | TC-DASH-01 to TC-DASH-05 | Tester 1 |
| Cage Management | TC-CAGE-01 to TC-CAGE-07 | Tester 1 |
| Bulk Hen Import | TC-BULK-01 to TC-BULK-04 | Tester 1 |
| Egg Logging | TC-EGG-01 to TC-EGG-08 | Tester 2 |
| Feed Management | TC-FEED-01 to TC-FEED-06 | Tester 2 |
| Mortality Logging | TC-MORT-01 to TC-MORT-06 | Tester 2 |
| Environment Monitoring | TC-ENV-01 to TC-ENV-07 | Tester 2 |
| Alerts | TC-ALERT-01 to TC-ALERT-03 | Tester 2 |
| Account Settings | TC-ACCT-01 to TC-ACCT-06 | Tester 2 |
| **Total** | **58 test cases** | |

---

*Excluded from this cycle: Forecast, Analytics, Reports*
