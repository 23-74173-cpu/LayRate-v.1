# Report Print & Document Design — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the reports output to look like a formal printed document — letterhead, metadata strip, summary stats, branded data table, and signature block — that renders cleanly both on screen and when printed.

**Architecture:** The ReportController gains a `buildSummary()` helper that computes per-type aggregate stats from raw DB queries (separate from the row-formatting pipeline). The view is split into a `.no-print` filter section and a `#report-document` printable section. Print CSS in `@push('head')` hides all app chrome (sidebar, nav, filter form) and applies A4 margins.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS CDN, `@media print` CSS, PHP stdClass for summary data.

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `app/Http/Controllers/ReportController.php` | Modify | Add `buildSummary()`, pass `$summary` to view |
| `resources/views/reports.blade.php` | Modify | Replace output section with document template + print CSS |

---

### Task 1: Add summary stats to ReportController

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`

- [ ] **Step 1: Add `buildSummary()` method and wire it into `index()` and `exportCsv()`**

Replace the entire `index()` method and add `buildSummary()` in `app/Http/Controllers/ReportController.php`:

```php
public function index(Request $request)
{
    $type   = $request->get('type', 'production');
    $from   = $request->get('from');
    $to     = $request->get('to');
    $cageId = $request->get('cage', 'all');
    $reason = $request->get('reason', 'all');

    $allCages = Cage::orderBy('cage_code')->get();
    $rows    = collect();
    $summary = null;

    if ($from && $to) {
        $rows    = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);
        $summary = $this->buildSummary($type, $from, $to, $cageId, $reason, $allCages);
    }

    return view('reports', compact('type', 'from', 'to', 'cageId', 'reason', 'allCages', 'rows', 'summary'));
}
```

- [ ] **Step 2: Add the `buildSummary()` method**

Add this method to `ReportController` (after `buildReport()`):

```php
private function buildSummary($type, $from, $to, $cageId, $reason, $allCages): ?object
{
    $cageIds = $cageId === 'all'
        ? $allCages->pluck('id')
        : [$allCages->where('cage_code', $cageId)->first()?->id];

    return match($type) {
        'production' => (object) [
            'total_eggs'  => ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->sum('egg_count'),
            'avg_hdep'    => number_format(ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->avg('hdep') ?? 0, 1) . '%',
            'total_hens'  => ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->max('hen_count') ?? 0,
            'days'        => ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
        ],
        'feed' => (object) [
            'total_kg'    => number_format(FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->sum('feed_consumed_kg'), 1),
            'avg_per_day' => number_format(FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->avg('feed_consumed_kg') ?? 0, 1),
            'batches'     => FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('feed_batch_id')->count('feed_batch_id'),
            'days'        => FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
        ],
        'environment' => (object) [
            'avg_temp'    => number_format(EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->avg('temperature_c') ?? 0, 1) . '°C',
            'avg_hum'     => number_format(EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->avg('humidity_pct') ?? 0, 1) . '%',
            'readings'    => EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->count(),
            'alerts'      => EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->where(fn($q) => $q->where('temperature_c', '>', 30)->orWhere('humidity_pct', '>', 70))->count(),
        ],
        'mortality' => (object) [
            'total_deaths'  => MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->sum('count'),
            'top_cause'     => MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->selectRaw('reason, SUM(`count`) as total')->groupBy('reason')->orderByDesc('total')->value('reason') ?? '—',
            'most_affected' => optional($allCages->find(MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->selectRaw('cage_id, SUM(`count`) as total')->groupBy('cage_id')->orderByDesc('total')->value('cage_id')))->cage_code ?? '—',
            'days'          => MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
        ],
        default => null,
    };
}
```

- [ ] **Step 3: Verify syntax**

```powershell
php -l app/Http/Controllers/ReportController.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Verify summary query works**

```powershell
php artisan tinker --execute="
\$c = App\Models\Cage::orderBy('cage_code')->get();
\$rc = new App\Http\Controllers\ReportController();
\$ref = new ReflectionMethod(\$rc, 'buildSummary');
\$ref->setAccessible(true);
\$s = \$ref->invoke(\$rc, 'production', '2026-05-01', '2026-06-10', 'all', 'all', \$c);
echo 'eggs: ' . \$s->total_eggs . ', hdep: ' . \$s->avg_hdep . PHP_EOL;
"
```
Expected output: `eggs: <number>, hdep: <number>%`

---

### Task 2: Redesign the reports view

**Files:**
- Modify: `resources/views/reports.blade.php`

- [ ] **Step 1: Replace the entire file with the new document layout**

Write the following to `resources/views/reports.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Reports')

@push('head')
<style>
@media print {
    aside, header, .no-print { display: none !important; }
    body { overflow: visible !important; background: white !important; }
    .flex.h-screen.overflow-hidden { display: block !important; }
    .flex.flex-col.flex-1 { display: block !important; }
    .flex-1.overflow-auto { overflow: visible !important; }
    main { padding: 0 !important; background: white !important; }
    #report-document {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        margin: 0 !important;
        padding: 20mm 20mm 15mm 20mm !important;
        font-family: Georgia, 'Times New Roman', serif !important;
    }
    #report-document table { font-size: 9pt !important; }
    #report-document thead { display: table-header-group; }
    .signature-block { page-break-inside: avoid; }
    .no-screen { display: block !important; }
}
.no-screen { display: none; }
</style>
@endpush

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Filters (hidden on print) ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 no-print">
        <form method="GET" action="{{ route('reports') }}" class="flex flex-wrap items-end gap-4" id="reportForm">
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">REPORT TYPE</label>
                <select name="type" id="reportType"
                        class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C] min-w-[180px]">
                    <option value="production" {{ $type === 'production' ? 'selected' : '' }}>Production Report</option>
                    <option value="feed"        {{ $type === 'feed'       ? 'selected' : '' }}>Feed Report</option>
                    <option value="environment" {{ $type === 'environment'? 'selected' : '' }}>Environment Report</option>
                    <option value="mortality"   {{ $type === 'mortality'  ? 'selected' : '' }}>Mortality Report</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">FROM</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
            </div>
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">TO</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
            </div>
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                <select name="cage"
                        class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    <option value="all" {{ $cageId === 'all' ? 'selected' : '' }}>All Cages</option>
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $cageId === $c->cage_code ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
            </div>
            <div id="reasonFilter" class="{{ $type === 'mortality' ? '' : 'hidden' }}">
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">REASON</label>
                <select name="reason"
                        class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    <option value="all" {{ $reason === 'all' ? 'selected' : '' }}>All Reasons</option>
                    @foreach(\App\Models\MortalityLog::REASONS as $r)
                    <option value="{{ $r }}" {{ $reason === $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="bg-[#102A4C] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#1D4E8F] transition-colors">
                Generate Report
            </button>
            @if($rows->isNotEmpty())
            <a href="{{ route('reports.csv', request()->query()) }}"
               class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-4 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
            <button type="button" onclick="window.print()"
                    class="flex items-center gap-1.5 bg-[#102A4C] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#1D4E8F] transition-colors">
                <i data-lucide="printer" class="w-4 h-4"></i> Print
            </button>
            @endif
        </form>
    </div>

    {{-- ── Report Document ── --}}
    @if($rows->isNotEmpty())
    <div id="report-document" class="bg-white rounded-lg border border-[#D9D9D9] shadow-sm p-8 max-w-5xl mx-auto">

        {{-- Letterhead --}}
        <div class="flex items-start justify-between pb-4 mb-4 border-b-4 border-[#102A4C]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-[#102A4C] flex items-center justify-center shrink-0">
                    <i data-lucide="feather" class="w-5 h-5 text-white no-print"></i>
                    <span class="no-screen text-white font-bold text-sm">LR</span>
                </div>
                <div>
                    <div class="text-base font-bold text-[#102A4C] leading-tight">LayRate Poultry Farm</div>
                    <div class="text-xs text-[#6B7280]">Farm Monitor System</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-semibold text-[#102A4C] uppercase tracking-wide">
                    {{ ucfirst($type) }} Report
                </div>
                <div class="text-xs text-[#6B7280] mt-0.5">
                    {{ \Carbon\Carbon::parse($from)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
                </div>
            </div>
        </div>

        {{-- Metadata strip --}}
        <div class="flex flex-wrap gap-6 text-xs text-[#6B7280] mb-5 pb-3 border-b border-[#E8E8E8]">
            <span><span class="font-semibold text-[#333]">Cage:</span> {{ $cageId === 'all' ? 'All Cages' : $cageId }}</span>
            @if($type === 'mortality' && $reason !== 'all')
            <span><span class="font-semibold text-[#333]">Reason:</span> {{ $reason }}</span>
            @endif
            <span><span class="font-semibold text-[#333]">Generated:</span> {{ now()->format('M d, Y H:i') }}</span>
            <span><span class="font-semibold text-[#333]">Prepared by:</span> {{ auth()->user()->name }}</span>
            <span><span class="font-semibold text-[#333]">Records:</span> {{ $rows->count() }}</span>
        </div>

        {{-- Summary bar --}}
        @if($summary)
        <div class="flex flex-wrap gap-3 mb-5">
            @if($type === 'production')
                @foreach(['Total Eggs' => number_format($summary->total_eggs), 'Avg HDEP' => $summary->avg_hdep, 'Max Hens' => number_format($summary->total_hens), 'Days Covered' => $summary->days] as $label => $val)
                <div class="bg-[#F5F5F0] border border-[#D9D9D9] rounded-lg px-4 py-2 text-center min-w-[100px]">
                    <div class="text-[10px] tracking-wider text-[#6B7280]">{{ strtoupper($label) }}</div>
                    <div class="text-base font-semibold text-[#102A4C] mt-0.5">{{ $val }}</div>
                </div>
                @endforeach
            @elseif($type === 'feed')
                @foreach(['Total Consumed' => $summary->total_kg . ' kg', 'Avg per Day' => $summary->avg_per_day . ' kg', 'Batches Used' => $summary->batches, 'Days Covered' => $summary->days] as $label => $val)
                <div class="bg-[#F5F5F0] border border-[#D9D9D9] rounded-lg px-4 py-2 text-center min-w-[100px]">
                    <div class="text-[10px] tracking-wider text-[#6B7280]">{{ strtoupper($label) }}</div>
                    <div class="text-base font-semibold text-[#102A4C] mt-0.5">{{ $val }}</div>
                </div>
                @endforeach
            @elseif($type === 'environment')
                @foreach(['Avg Temperature' => $summary->avg_temp, 'Avg Humidity' => $summary->avg_hum, 'Total Readings' => number_format($summary->readings), 'Alert Readings' => $summary->alerts] as $label => $val)
                @php $isAlert = $label === 'Alert Readings' && $val > 0; @endphp
                <div class="bg-[#F5F5F0] border border-[#D9D9D9] rounded-lg px-4 py-2 text-center min-w-[100px]">
                    <div class="text-[10px] tracking-wider text-[#6B7280]">{{ strtoupper($label) }}</div>
                    <div class="text-base font-semibold mt-0.5 {{ $isAlert ? 'text-red-600' : 'text-[#102A4C]' }}">{{ $val }}</div>
                </div>
                @endforeach
            @elseif($type === 'mortality')
                @foreach(['Total Deaths' => $summary->total_deaths, 'Top Cause' => $summary->top_cause, 'Most Affected' => $summary->most_affected, 'Days Covered' => $summary->days] as $label => $val)
                @php $isDanger = $label === 'Total Deaths' && $val > 0; @endphp
                <div class="bg-[#F5F5F0] border border-[#D9D9D9] rounded-lg px-4 py-2 text-center min-w-[100px]">
                    <div class="text-[10px] tracking-wider text-[#6B7280]">{{ strtoupper($label) }}</div>
                    <div class="text-base font-semibold mt-0.5 {{ $isDanger ? 'text-red-600' : 'text-[#102A4C]' }}">{{ $val }}</div>
                </div>
                @endforeach
            @endif
        </div>
        @endif

        {{-- Data table --}}
        <div class="overflow-x-auto mb-8">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr style="background:#102A4C;">
                        @foreach(array_keys((array) $rows->first()) as $col)
                        <th class="text-left text-white px-4 py-2.5 text-[11px] tracking-wider font-semibold whitespace-nowrap">
                            {{ strtoupper(str_replace('_', ' ', $col)) }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                    @php
                        $arr = (array) $row;
                        $reasonColors = ['Disease'=>'#7f1d1d','Heat Stress'=>'#78350f','Injury'=>'#78350f','Predator'=>'#7f1d1d'];
                    @endphp
                    <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-[#F9F9F7]' }} border-b border-[#EBEBEB]">
                        @foreach($arr as $key => $val)
                        @php
                            $cageColor   = $key === 'cage'   ? match($val){'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>null} : null;
                            $reasonColor = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                            $style = $cageColor   ? "color:{$cageColor};font-weight:600"
                                   : ($reasonColor ? "color:{$reasonColor}" : 'color:#333333');
                        @endphp
                        <td class="px-4 py-2.5 {{ in_array($key, ['date','datetime']) ? 'font-mono text-xs' : 'text-sm' }} whitespace-nowrap"
                            style="{{ $style }}">{{ $val }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Signature block --}}
        <div class="signature-block grid grid-cols-2 gap-16 pt-6 border-t border-[#D9D9D9] mt-4">
            <div>
                <div class="text-xs text-[#6B7280] mb-8">Prepared by:</div>
                <div class="border-b border-[#333] mb-1"></div>
                <div class="text-xs text-[#6B7280]">Name / Position / Date</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-8">Noted by:</div>
                <div class="border-b border-[#333] mb-1"></div>
                <div class="text-xs text-[#6B7280]">Name / Position / Date</div>
            </div>
        </div>

        {{-- Print footer (only visible on print) --}}
        <div class="no-screen mt-4 text-center text-xs text-gray-400">
            LayRate Farm Monitor System &nbsp;·&nbsp; Generated {{ now()->format('M d, Y H:i') }}
        </div>

    </div>
    @elseif(request()->has('from'))
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#6B7280]">
        No data found for the selected filters.
    </div>
    @endif

</main>
@endsection

@push('scripts')
<script>
lucide.createIcons();
document.getElementById('reportType').addEventListener('change', function () {
    document.getElementById('reasonFilter').classList.toggle('hidden', this.value !== 'mortality');
});
</script>
@endpush
```

- [ ] **Step 2: Verify syntax**

```powershell
php -l resources/views/reports.blade.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Clear view cache**

```powershell
php artisan view:clear
```
Expected: `Compiled views cleared successfully.`

- [ ] **Step 4: Smoke test all four report types in browser**

Navigate to `http://127.0.0.1:8000/reports`, generate each type and verify:
- **Production** — letterhead shows, 4 summary pills (Total Eggs, Avg HDEP, Max Hens, Days Covered), table has navy header, cage codes are colored
- **Feed** — 4 summary pills (Total Consumed, Avg per Day, Batches Used, Days Covered)
- **Environment** — 4 summary pills (Avg Temperature, Avg Humidity, Total Readings, Alert Readings — red if > 0)
- **Mortality** — 4 summary pills (Total Deaths, Top Cause, Most Affected, Days Covered), reason column colored
- Signature block visible at the bottom with "Prepared by / Noted by" lines
- Filter form has "Print" button

- [ ] **Step 5: Test print preview**

In Chrome: open a generated report → Ctrl+P (or click Print button).  
Verify in the print preview:
- Sidebar is gone
- Filter form is gone
- Letterhead fills the page width
- Summary pills visible
- Table header row is navy
- Signature block at the bottom
- No horizontal scroll / overflow

- [ ] **Step 6: Commit**

```powershell
git add app/Http/Controllers/ReportController.php resources/views/reports.blade.php
git commit -m "feat: redesign reports as printable official document with letterhead, summary stats, and signature block"
```
