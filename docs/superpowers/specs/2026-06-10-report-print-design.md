# Report Print & Document Design

**Date:** 2026-06-10
**Project:** LayRate Poultry Farm Management System
**Scope:** Redesign the reports output view to look like a formal printed document

---

## Goal

Replace the plain data table in `/reports` with a properly formatted document that looks professional both on screen and when printed (A4/Letter). The filter form stays at the top; the document preview appears below it.

---

## Document Layout (top → bottom)

### 1. Letterhead
- Left: LayRate feather icon + "LayRate Poultry Farm" (bold) + "Farm Monitor System" (small subtitle)
- Right: Report title (e.g. "Production Report") + date range
- Separated from body by a 3px dark navy (#102A4C) horizontal rule

### 2. Metadata Strip
Single row, small text:
- Cage filter (All / CAGE-A / etc.)
- Generated: date + time
- Prepared by: logged-in user name
- Records: count

### 3. Summary Bar
Compact metric pills, one row, per report type:
- **Production:** Total Eggs · Avg HDEP · Total Hens · Days Covered
- **Feed:** Total Consumed (kg) · Avg per Day · Batches Used
- **Environment:** Avg Temperature · Avg Humidity · Alert Readings
- **Mortality:** Total Deaths · Top Cause · Most Affected Cage · Days Covered

### 4. Data Table
- Full-width, navy header row (white text)
- Zebra striping (white / #F9F9F7)
- Cage code column colored in brand colors (CAGE-A green, CAGE-B blue, etc.)
- Column headers uppercase, letter-spaced
- `thead` marked for print repetition across pages

### 5. Signature Block
Two-column, bottom of document:
```
Prepared by: ___________________     Noted by: ___________________
Name / Position / Date               Name / Position / Date
```

---

## Print Behavior (`@media print`)

- Hide: `aside` (sidebar), `header`, `.no-print` class (filter form, flash messages, Export CSV/Print buttons)
- `body`, `.flex.h-screen`, scroll containers: set to `display:block; overflow:visible`
- Document area: full page width, margins 20mm, no box-shadow, no border-radius
- Font: switch to serif for print
- `thead { display: table-header-group }` — headers repeat on multi-page tables
- `tfoot, .signature-block { page-break-inside: avoid }`

---

## Implementation Files

- `app/Http/Controllers/ReportController.php` — pass summary stats per report type
- `resources/views/reports.blade.php` — replace output section with document template

---

## Out of Scope

- PDF export (browser print-to-PDF covers this)
- Storing/archiving generated reports
- Charts or graphs in the printed document
