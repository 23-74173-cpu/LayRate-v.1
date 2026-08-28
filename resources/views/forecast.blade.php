@extends('layouts.app')
@section('title', 'Forecast')

@section('content')
<div class="space-y-5">

    <x-page-header title="Forecast" subtitle="Project egg production based on historical egg count trends" />

    @php
        $calendarMonth = $calendarDate->copy();
        $daysInMonth = $calendarMonth->daysInMonth;
        $monthStart = $calendarMonth->copy()->startOfMonth();
        $startOffset = ($monthStart->dayOfWeek + 6) % 7;
        $totalCells = $startOffset + $daysInMonth;
        $endOffset = (7 - ($totalCells % 7)) % 7;
        $weeksInMonth = ($totalCells + $endOffset) / 7;
        $calendarToday = now();
        $forecastMap = collect($forecasts ?? [])->keyBy(fn($f) => is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date);
    @endphp

    {{-- ── Forecast KPI Cards ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Weeks in month</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $weeksInMonth }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Days in month</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $daysInMonth }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Forecast days</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ count($forecastMap) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Current week</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $calendarToday->weekOfMonth }}</div>
        </div>
    </div>

    @php
        $scopeLabel = match($scope) { 'farm' => 'Whole Farm', 'breed' => $breed ?? '', default => $cageCode ?? '' };
        $forecastCount = $forecastDataDays ?? 0;
        $pct = min(100, ($forecastCount / 90) * 100);
    @endphp
    @if(!($hasEnoughData ?? true))
    <style>
        /* Lock only the main content, not the sidebar. Desktop sidebar is in
           flow at 16rem (collapsed: 4rem); on mobile the sidebar is a drawer
           so the overlay spans full width. */
        #forecastLockOverlay {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        #forecastLockOverlay > .backdrop {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background-color: rgba(35,39,50,0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        @media (min-width: 1024px) {
            #forecastLockOverlay { left: 16rem; }
            #forecastLockOverlay > .backdrop { left: 16rem; }
            html.sidebar-collapsed #forecastLockOverlay { left: 4rem; }
            html.sidebar-collapsed #forecastLockOverlay > .backdrop { left: 4rem; }
        }
        #forecastLockOverlay { pointer-events: auto; }
    </style>
    <div id="forecastLockOverlay" role="dialog" aria-modal="true">
        <div class="backdrop"></div>

        <div class="relative w-full max-w-md rounded-2xl p-6 text-center"
             style="background-color: #ffffff; box-shadow: 0 24px 64px rgba(0,0,0,0.35);">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: #fdf3e0;">
                <i data-lucide="hourglass" class="w-7 h-7" style="color: #8a5a00;"></i>
            </div>
            <h2 class="text-lg font-semibold" style="color: #1f1f1f;">Insufficient Forecast Data</h2>
            <p class="text-sm mt-2" style="color: #615d59;">
                You need at least <strong>90 days</strong> of production records to generate a forecast.
            </p>

            <div class="mt-5">
                <div class="flex items-center justify-between text-xs mb-1" style="color: #615d59;">
                    <span>{{ $scopeLabel }}: <strong style="color:#8a5a00;">{{ $forecastCount }}</strong>/90 days collected</span>
                    <span>{{ number_format($pct, 0) }}%</span>
                </div>
                <div class="w-full rounded-full h-2 overflow-hidden" style="background-color: #f3e3bf;">
                    <div class="h-2 rounded-full transition-all" style="width: {{ $pct }}%; background-color: #c2703e;"></div>
                </div>
            </div>

            <div class="mt-5 text-xs leading-relaxed" style="color: #6B7280;">
                Keep logging eggs daily. Each recorded day adds to your history.
                You can also download the forecast input sheet, fill it with historical data,
                and import it to reach 90 days faster.
            </div>

            <div class="mt-5 grid grid-cols-1 gap-2 text-center">
                <button type="button" id="lockDownloadBtn"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-white hover:brightness-95 transition-colors"
                        style="background-color:#002D5E;">
                    <i data-lucide="download" class="w-4 h-4"></i> Download forecast sheet
                </button>
                <button type="button" id="lockInputRecordsBtn"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
                        style="color:#002D5E; border:1px solid #d6e0f2; background-color:#eef2fb;">
                    <i data-lucide="table" class="w-4 h-4"></i> View input records status
                </button>
                <button type="button" id="lockImportBtn"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
                        style="color:#1f6b3a; border:1px solid #cfe8d6; background-color:#e8f5ec;">
                    <i data-lucide="upload" class="w-4 h-4"></i> Import function
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- View forecast input records status --}}
    <div id="inputRecordsModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-auto overflow-hidden max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#F0F0F0]">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-[#002D5E]/10 flex items-center justify-center">
                        <i data-lucide="database" class="w-4 h-4 text-[#002D5E]"></i>
                    </div>
                    <h3 class="text-base font-semibold text-[#333333]">Forecast Input Records</h3>
                </div>
                <button type="button" id="closeInputRecords" class="text-[#6B7280] hover:text-[#333333] transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-5 overflow-y-auto">
                <div id="inputRecordsContent" class="text-center py-8 text-[#6B7280] text-sm">
                    <i data-lucide="loader" class="w-6 h-6 mx-auto mb-2 animate-spin" style="color:#0075de;"></i>
                    Loading records…
                </div>
            </div>
        </div>
    </div>

    {{-- ── Forecast Workspace (Inputs + Chart + Metrics) ── --}}
    @include('forecast._workspace')

    {{-- ── Production Calendar ── --}}
    @include('forecast._calendar')

{{-- Download Template Modal --}}
<div id="downloadTemplateModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-auto overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#F0F0F0]">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#002D5E]/10 flex items-center justify-center">
                    <i data-lucide="download" class="w-4 h-4 text-[#002D5E]"></i>
                </div>
                <h3 class="text-base font-semibold text-[#333333]">Download input sheet</h3>
            </div>
            <button type="button" id="closeDownloadTemplateModal" class="text-[#6B7280] hover:text-[#333333] transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-5">
            <p class="text-sm text-[#6B7280] mb-4">
                Choose the date range to record. Pre-filled columns are locked; only egg count, environment, feed, and mortality columns are editable.
            </p>
            <form method="GET" action="{{ route('forecast.template') }}" id="downloadTemplateForm" data-turbo="false">
                @php
                    $defaultEndDate = now()->format('Y-m-d');
                    $defaultStartDate = now()->subDays(89)->format('Y-m-d');
                @endphp
                <input type="hidden" name="end_date" value="{{ $defaultEndDate }}">
                <div class="mb-4">
                    <label for="templateStartDate" class="block text-sm text-[#333333] mb-1">Start date</label>
                    <input type="date" name="start_date" id="templateStartDate" required
                           value="{{ $defaultStartDate }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#002D5E]">
                </div>
                <div class="mb-4">
                    <label for="templateEndDate" class="block text-sm text-[#333333] mb-1">End date</label>
                    <input type="date" id="templateEndDate" value="{{ $defaultEndDate }}" readonly
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-[#F5F6F8] text-[#6B7280] cursor-not-allowed">
                    <p class="text-xs text-[#6B7280] mt-1">The sheet always ends today.</p>
                </div>
                <button type="submit" class="w-full bg-[#002D5E] text-white py-3 rounded-lg text-sm font-medium hover:bg-[#001F42] transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="download" class="w-5 h-5 shrink-0"></i>
                    <span>Download sheet</span>
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Forecast generation loading overlay --}}
<div id="forecastLoadingOverlay" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl p-8 max-w-sm w-full mx-4 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#002D5E]/10 mb-4">
            <svg class="animate-spin h-6 w-6 text-[#002D5E]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-[#333333] mb-1">Generating Forecast</h3>
        <p class="text-sm text-[#6B7280] mb-4">Please wait while the model trains and produces predictions...</p>
        <p id="forecastStatusText" class="text-xs text-[#002D5E] font-medium mb-2 h-4">Loading historical data...</p>
        <div class="w-full bg-[#F0F0F0] rounded-full h-2.5 overflow-hidden">
            <div id="forecastProgressBar" class="bg-[#002D5E] h-2.5 rounded-full" style="width: 0%"></div>
        </div>
        <p id="forecastProgressText" class="text-xs text-[#6B7280] mt-2">0%</p>
    </div>
</div>

{{-- Floating Action Button --}}
<div class="fixed bottom-6 right-3 z-40 flex flex-col items-end gap-3 pointer-events-none">
    {{-- FAB Menu --}}
    <div id="fabMenu" class="flex flex-col items-end gap-2 mb-1 mr-2 transition-all duration-200 opacity-0 invisible translate-y-4 pointer-events-auto">
        <button type="button" id="fabDownloadBtn" class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Download input sheet</span>
            <div class="w-8 h-8 rounded-full bg-[#002D5E]/10 flex items-center justify-center">
                <i data-lucide="download" class="w-4 h-4 text-[#002D5E]"></i>
            </div>
        </button>
        <a href="{{ route('forecast.input-records.download') }}" data-turbo="false"
           class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Download Production Data</span>
            <div class="w-8 h-8 rounded-full bg-[#C2703E]/10 flex items-center justify-center">
                <i data-lucide="database" class="w-4 h-4 text-[#C2703E]"></i>
            </div>
        </a>
        <button type="button" id="fabInputRecordsBtn" class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>View input records status</span>
            <div class="w-8 h-8 rounded-full bg-[#0075de]/10 flex items-center justify-center">
                <i data-lucide="table" class="w-4 h-4 text-[#0075de]"></i>
            </div>
        </button>
        <button type="button" id="fabImportBtn" class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Import production data</span>
            <div class="w-8 h-8 rounded-full bg-[#2D7D46]/10 flex items-center justify-center">
                <i data-lucide="upload" class="w-4 h-4 text-[#2D7D46]"></i>
            </div>
        </button>
        <button type="button" id="fabExportBtn" class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Export Forecast Report</span>
            <div class="w-8 h-8 rounded-full bg-[#6B4C8A]/10 flex items-center justify-center">
                <i data-lucide="file-down" class="w-4 h-4 text-[#6B4C8A]"></i>
            </div>
        </button>
    </div>

    {{-- FAB Toggle --}}
    <button type="button" id="fabToggle"
        class="w-16 h-12 rounded-full bg-surface text-ink border border-hairline shadow-soft hover:bg-canvas-soft transition-colors flex items-center justify-center flex-shrink-0 pointer-events-auto"
        aria-label="Open menu" aria-expanded="false">
        <i data-lucide="plus" id="fabIcon" class="w-6 h-6 transition-transform duration-200 ease-out"></i>
    </button>
</div>

{{-- Import Modal --}}
<div id="importModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-auto overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#F0F0F0]">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#2D7D46]/10 flex items-center justify-center">
                    <i data-lucide="upload-cloud" class="w-4 h-4 text-[#2D7D46]"></i>
                </div>
                <h3 class="text-base font-semibold text-[#333333]">Import forecast data</h3>
            </div>
            <button type="button" id="closeImportModal" class="text-[#6B7280] hover:text-[#333333] transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-5">
            <p class="text-sm text-[#6B7280] mb-4">
                Import a filled <strong>.xlsx</strong> forecast input sheet. The sheet must include <strong>Date</strong> and <strong>Cage_Code</strong>; other columns (Breed, Hen_Count, Egg_Count, etc.) are recommended but not required.
            </p>
            <p class="text-xs text-[#6B7280] mt-2 flex items-start gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                Re-uploading will update existing records for matching dates/cages, not duplicate them.
            </p>

            {{-- Inline import feedback messages --}}
            <div id="importFeedback" class="hidden mb-3 rounded-lg p-3 text-sm">
                <div class="flex items-start gap-2">
                    {{-- No data-lucide until showImportFeedback() sets a real icon name —
                         an empty attribute here gets picked up by the global lucide.createIcons()
                         scan on every page load and logs "icon name was not found". --}}
                    <i id="importFeedbackIcon" class="w-4 h-4 shrink-0 mt-0.5"></i>
                    <div class="flex-1 min-w-0">
                        <textarea id="importFeedbackMessage" readonly rows="3"
                            class="w-full bg-transparent border-0 p-0 text-inherit resize-none focus:ring-0 focus:outline-none select-all"
                        ></textarea>
                        <button type="button" id="copyImportFeedbackBtn"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-[#002D5E] hover:text-[#001b3d]">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            <span>Copy message</span>
                        </button>
                    </div>
                </div>
            </div>

            @can('admin')
            <div id="forecastImportSection">
                {{-- Step 1: File selection --}}
                <div id="importStepFile">
                    <div id="dropZone" class="group relative border-2 border-dashed border-[#D9D9D9] rounded-lg p-5 hover:border-[#2D7D46] hover:bg-[#F5F6F8] transition-all cursor-pointer text-center mb-3">
                        <input type="file" name="forecast_file" id="forecastFileInput" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div id="dropZonePrompt" class="pointer-events-none">
                            <div class="w-10 h-10 rounded-full bg-[#F5F6F8] group-hover:bg-white flex items-center justify-center mx-auto mb-2 transition-colors">
                                <i data-lucide="file-spreadsheet" class="w-5 h-5 text-[#6B7280]"></i>
                            </div>
                            <p class="text-sm text-[#333333] font-medium">Click or drag Excel file here</p>
                            <p class="text-xs text-[#6B7280] mt-1">.xlsx only, max 10 MB</p>
                        </div>
                        <div id="fileSelectedPrompt" class="hidden pointer-events-none">
                            <div class="w-10 h-10 rounded-full bg-[#D5E8D4] flex items-center justify-center mx-auto mb-2">
                                <i data-lucide="check-circle" class="w-5 h-5 text-[#2D7D46]"></i>
                            </div>
                            <p class="text-sm text-[#333333] font-medium truncate px-2" id="selectedFileName">No file selected</p>
                            <p class="text-xs text-[#6B7280] mt-1">Click or drop another file to replace</p>
                        </div>
                    </div>
                    @error('forecast_file')
                    <p class="text-xs text-red-600 mb-2">{{ $message }}</p>
                    @enderror
                    <x-button type="button" id="previewImportBtn" class="w-full py-3 shadow-md" disabled>
                        <i data-lucide="scan-eye" class="w-5 h-5 shrink-0"></i>
                        <span id="previewBtnText">Preview Import</span>
                    </x-button>
                </div>

                {{-- Step 2: Preview results (hidden by default) --}}
                <div id="importStepPreview" class="hidden">
                    <div class="mb-3 rounded-lg border border-[#E5E7EB] overflow-hidden">
                        <div class="bg-[#F5F6F8] px-4 py-2.5 border-b border-[#E5E7EB]">
                            <p class="text-xs font-semibold text-[#333333] uppercase tracking-wide">Preview</p>
                        </div>
                        <div class="p-4 space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[#6B7280]">Total rows</span>
                                <span class="font-medium text-[#333333]" id="previewTotalRows">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#6B7280]">Valid rows</span>
                                <span class="font-medium text-[#2D7D46]" id="previewValidRows">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#6B7280]">Skipped rows</span>
                                <span class="font-medium text-[#B45309]" id="previewInvalidRows">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#6B7280]">Date range</span>
                                <span class="font-medium text-[#333333]" id="previewDateRange">—</span>
                            </div>
                        </div>
                    </div>

                    {{-- Invalid rows detail --}}
                    <div id="previewInvalidDetail" class="hidden mb-3">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 overflow-hidden">
                            <div class="px-4 py-2.5 border-b border-amber-200">
                                <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide">Skipped rows</p>
                            </div>
                            <div class="max-h-40 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-amber-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-1.5 text-left font-medium text-amber-800">Row</th>
                                            <th class="px-4 py-1.5 text-left font-medium text-amber-800">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewInvalidRowsTable" class="divide-y divide-amber-100"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Missing columns warning --}}
                    <div id="previewMissingCols" class="hidden mb-3">
                        <div class="rounded-lg border border-red-200 bg-red-50 overflow-hidden">
                            <div class="px-4 py-2.5 border-b border-red-200">
                                <p class="text-xs font-semibold text-red-800 uppercase tracking-wide">Missing columns</p>
                            </div>
                            <div class="px-4 py-2.5">
                                <p id="previewMissingColsMessage" class="text-sm text-red-700"></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <x-button type="button" id="cancelImportBtn" variant="secondary" class="flex-1 py-3">
                            <i data-lucide="x" class="w-4 h-4 shrink-0"></i>
                            <span>Cancel</span>
                        </x-button>
                        <x-button type="button" id="confirmImportBtn" class="flex-1 py-3 shadow-md">
                            <i data-lucide="check" class="w-4 h-4 shrink-0"></i>
                            <span id="confirmBtnText">Confirm Import</span>
                        </x-button>
                    </div>
                </div>
            </div>
            @endcan
        </div>
    </div>
</div>

{{-- Export Forecast Modal --}}
<div id="exportForecastModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-auto overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#F0F0F0]">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#6B4C8A]/10 flex items-center justify-center">
                    <i data-lucide="file-down" class="w-4 h-4 text-[#6B4C8A]"></i>
                </div>
                <h3 class="text-base font-semibold text-[#333333]">Export forecast</h3>
            </div>
            <button type="button" id="closeExportForecastModal" class="text-[#6B7280] hover:text-[#333333] transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-5 space-y-3">
            <p class="text-sm text-[#6B7280] mb-2">Choose a format to export the forecast data and chart.</p>
            <button type="button" data-export-format="pdf"
                class="export-forecast-btn w-full flex items-center gap-3 bg-white border border-[#D9D9D9] rounded-lg px-4 py-3 hover:bg-[#F5F6F8] transition-colors text-left">
                <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                    <i data-lucide="file-text" class="w-4 h-4 text-red-600"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-[#333333]">PDF</div>
                    <div class="text-xs text-[#6B7280]">Printable document with calendar + chart</div>
                </div>
            </button>
            <button type="button" data-export-format="excel"
                class="export-forecast-btn w-full flex items-center gap-3 bg-white border border-[#D9D9D9] rounded-lg px-4 py-3 hover:bg-[#F5F6F8] transition-colors text-left">
                <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
                    <i data-lucide="table" class="w-4 h-4 text-green-600"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-[#333333]">Excel</div>
                    <div class="text-xs text-[#6B7280]">Spreadsheet with forecast data rows + chart</div>
                </div>
            </button>
            <button type="button" data-export-format="csv"
                class="export-forecast-btn w-full flex items-center gap-3 bg-white border border-[#D9D9D9] rounded-lg px-4 py-3 hover:bg-[#F5F6F8] transition-colors text-left">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4 text-blue-600"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-[#333333]">CSV</div>
                    <div class="text-xs text-[#6B7280]">Plain-text data table</div>
                </div>
            </button>
        </div>
    </div>
</div>

{{-- Export loading overlay --}}
<div id="forecastExportLoadingOverlay" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl p-8 max-w-sm w-full mx-4 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#6B4C8A]/10 mb-4">
            <svg class="animate-spin h-6 w-6 text-[#6B4C8A]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-[#333333] mb-1">Exporting forecast</h3>
        <p class="text-sm text-[#6B7280] mb-4">Capturing chart and generating file...</p>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    if (window.__layrateForecastInitialized) return;
    window.__layrateForecastInitialized = true;

    function toggleFab() {
        const fabMenu = document.getElementById('fabMenu');
        const fabIcon = document.getElementById('fabIcon');
        const fabToggle = document.getElementById('fabToggle');
        if (!fabMenu) return;
        const isOpen = !fabMenu.classList.contains('invisible');

        if (isOpen) {
            fabMenu.classList.add('invisible', 'opacity-0', 'translate-y-4');
            fabMenu.classList.remove('opacity-100', 'translate-y-0');
            if (fabIcon) fabIcon.style.transform = 'rotate(0deg)';

            if (fabToggle) {
                fabToggle.setAttribute('aria-expanded', 'false');
                fabToggle.setAttribute('aria-label', 'Open menu');
            }
        } else {
            fabMenu.classList.remove('invisible', 'opacity-0', 'translate-y-4');
            fabMenu.classList.add('opacity-100', 'translate-y-0');
            if (fabIcon) fabIcon.style.transform = 'rotate(45deg)';

            if (fabToggle) {
                fabToggle.setAttribute('aria-expanded', 'true');
                fabToggle.setAttribute('aria-label', 'Close menu');
            }
        }
    }

    document.addEventListener('click', function() {
        const m = document.getElementById('fabMenu');
        if (m && !m.classList.contains('invisible')) {
            toggleFab();
        }
    });

    var _forecastIsSubmitting = false;

    var FORECAST_STORAGE_KEYS = {
        startTime: 'layrate_forecast_start_time',
        expectedDuration: 'layrate_forecast_expected_duration_ms',
    };

    var FORECAST_DEFAULT_DURATIONS = {
        cage: { 1: 5000, 7: 5000, 14: 8000, 30: 12000 },
        breed: { 1: 6000, 7: 6000, 14: 10000, 30: 15000 },
        farm: { 1: 8000, 7: 8000, 14: 14000, 30: 20000 },
    };

    var FORECAST_MIN_DURATION = 3000;
    var FORECAST_MAX_DURATION = 280000;

    function resolveForecastDuration(scope, horizon) {
        var saved = localStorage.getItem(FORECAST_STORAGE_KEYS.expectedDuration);
        if (saved) {
            var parsed = parseInt(saved, 10);
            if (!isNaN(parsed) && parsed > 0) {
                return Math.max(FORECAST_MIN_DURATION, Math.min(FORECAST_MAX_DURATION, parsed));
            }
        }
        var scopeMap = FORECAST_DEFAULT_DURATIONS[scope] || FORECAST_DEFAULT_DURATIONS.cage;
        return scopeMap[horizon] || scopeMap[7] || FORECAST_MIN_DURATION;
    }
    window.resolveForecastDuration = resolveForecastDuration;

    const STATUS_MESSAGES = [
        'Loading historical data...',
        'Training SARIMA model...',
        'Training XGBoost model...',
        'Evaluating model performance...',
        'Generating predictions...',
        'Saving forecast results...',
    ];

    window.startForecastProgress = function(expectedDuration) {
        const progressBar = document.getElementById('forecastProgressBar');
        const progressText = document.getElementById('forecastProgressText');
        const statusText = document.getElementById('forecastStatusText');
        if (!progressBar) return;

        progressBar.style.width = '0%';
        if (progressText) progressText.textContent = '0%';
        if (statusText) statusText.textContent = STATUS_MESSAGES[0];

        const startTime = Date.now();

        function updateProgress() {
            const elapsed = Date.now() - startTime;
            const ratio = Math.min(elapsed / expectedDuration, 1);
            const eased = 1 - Math.pow(1 - ratio, 3);
            const progress = Math.min(95, eased * 95);

            progressBar.style.width = progress + '%';
            if (progressText) progressText.textContent = Math.round(progress) + '%';

            const messageIndex = Math.min(
                STATUS_MESSAGES.length - 1,
                Math.floor(ratio * STATUS_MESSAGES.length)
            );
            if (statusText) {
                statusText.textContent = STATUS_MESSAGES[messageIndex];
            }

            if (progress < 95) {
                requestAnimationFrame(updateProgress);
            }
        }

        requestAnimationFrame(updateProgress);
    };

    function recordForecastDuration() {
        var start = sessionStorage.getItem(FORECAST_STORAGE_KEYS.startTime);
        if (!start) return;
        var actual = Date.now() - parseInt(start, 10);
        sessionStorage.removeItem(FORECAST_STORAGE_KEYS.startTime);
        if (actual > 1000) {
            localStorage.setItem(FORECAST_STORAGE_KEYS.expectedDuration, actual);
        }
    }

    function initForecastLoading() {
        recordForecastDuration();
    }

    // ── Async forecast submission ───────────────────────────────────────────
    // /forecast/generate used to be a native form POST that blocked on the
    // server running a Python subprocess synchronously for up to 300s. It now
    // returns immediately with a forecast_run_id; this polls
    // GET /forecast/status/{id} until the job finishes, then Turbo-visits the
    // results URL — reusing the exact page the old synchronous redirect used
    // to land on, so nothing about how results are *displayed* changes, only
    // how completion is *detected*. Shared between forecastForm (this file)
    // and forecastDayForm (forecast/_calendar.blade.php) via window, since a
    // Turbo Frame partial can't see this file's local scope.
    var FORECAST_POLL_INTERVAL_MS = 2000;
    // A bit above executePythonForecast()'s own 300s Process timeout, so a
    // slow-but-legitimate run isn't abandoned client-side before the server
    // itself would have given up.
    var FORECAST_POLL_TIMEOUT_MS = 6 * 60 * 1000;

    function resetForecastSubmitUi() {
        _forecastIsSubmitting = false;
        var overlay = document.getElementById('forecastLoadingOverlay');
        var btn = document.getElementById('generateForecastBtn');
        var btnText = document.getElementById('btnText');
        if (overlay) overlay.style.display = 'none';
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Generate Forecast';
    }

    function pollForecastStatus(pollUrl) {
        var deadline = Date.now() + FORECAST_POLL_TIMEOUT_MS;

        function tick() {
            fetch(pollUrl, { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'completed' && data.redirect_url) {
                        // recordForecastDuration() is not called here — it
                        // already runs via initForecastLoading() on the
                        // turbo:load this visit triggers, same as it did
                        // after the old synchronous redirect.
                        if (window.Turbo) {
                            window.Turbo.visit(data.redirect_url);
                        } else {
                            window.location.href = data.redirect_url;
                        }
                        return;
                    }
                    if (data.status === 'failed') {
                        resetForecastSubmitUi();
                        if (window.showNotification) {
                            window.showNotification(data.error_message || 'Forecast generation failed.', 'error');
                        }
                        return;
                    }
                    if (Date.now() > deadline) {
                        resetForecastSubmitUi();
                        if (window.showNotification) {
                            window.showNotification('Forecast is taking longer than expected. It will finish in the background — check back shortly.', 'warning');
                        }
                        return;
                    }
                    setTimeout(tick, FORECAST_POLL_INTERVAL_MS);
                })
                .catch(function() {
                    // Transient network hiccup — keep polling instead of giving
                    // up on the first failed request.
                    if (Date.now() > deadline) {
                        resetForecastSubmitUi();
                        return;
                    }
                    setTimeout(tick, FORECAST_POLL_INTERVAL_MS);
                });
        }

        tick();
    }

    window.submitForecastFormAsync = function(form) {
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: new FormData(form)
        })
        .then(function(response) {
            if (!response.ok) {
                return response.json().catch(function() { return {}; }).then(function(body) {
                    throw new Error(body.message || ('Failed to start forecast generation (' + response.status + ').'));
                });
            }
            return response.json();
        })
        .then(function(data) {
            pollForecastStatus(data.poll_url);
        })
        .catch(function(err) {
            resetForecastSubmitUi();
            if (window.showNotification) {
                window.showNotification(err.message || 'Failed to start forecast generation.', 'error');
            }
        });
    };

    // Use document-level delegation for the forecast form submit so it survives
    // Turbo Frame content replacement (standalone submit handler on the form
    // element is lost when the frame's innerHTML is swapped).
    document.addEventListener('submit', function(e) {
        if (e.target.id !== 'forecastForm') return;
        if (_forecastIsSubmitting) {
            e.preventDefault();
            return;
        }

        _forecastIsSubmitting = true;
        e.preventDefault();

        const form = e.target;
        const overlay = document.getElementById('forecastLoadingOverlay');
        const btn = document.getElementById('generateForecastBtn');
        const btnText = document.getElementById('btnText');

        if (!overlay || !btn) return;

        const scopeInput = form.querySelector('input[name="scope"]');
        const horizonInput = form.querySelector('input[name="horizon"]:checked');
        const scope = scopeInput ? scopeInput.value : 'cage';
        const horizon = horizonInput ? parseInt(horizonInput.value, 10) : 7;
        const expectedDuration = resolveForecastDuration(scope, horizon);

        overlay.style.display = 'flex';
        btn.disabled = true;
        if (btnText) {
            btnText.textContent = 'Generating...';
        }

        sessionStorage.setItem('layrate_forecast_start_time', Date.now());

        window.startForecastProgress(expectedDuration);

        requestAnimationFrame(function() {
            setTimeout(function() {
                window.submitForecastFormAsync(form);
            }, 50);
        });
    });

    // Reset the submission lock on every page load / frame load so a failed
    // submission doesn't permanently prevent retries.
    document.addEventListener('turbo:load', function() { _forecastIsSubmitting = false; });
    document.addEventListener('turbo:frame-load', function() { _forecastIsSubmitting = false; });
    document.addEventListener('turbo:before-cache', function() {
        _forecastIsSubmitting = false;
        // Turbo caches the page DOM but not element-level listeners, and the
        // window flag below survives navigation. Without this reset, returning
        // to /forecast via Turbo skips re-binding the dropZone drag/preview
        // handlers, so drag-and-drop silently stops responding. Frame actions
        // don't fire turbo:before-cache, so this can't double-bind on them.
        window.__forecastActionsBound = false;
    });

    document.addEventListener('turbo:load', function() {
        // Every element bound below lives outside the turbo-frames and persists
        // across frame navigations, but turbo:load re-fires on each frame action
        // (e.g. next/prev month), which double-bound the FAB toggle so one click
        // opened then immediately closed the menu. Bind once; still refresh the
        // forecast loading state on every load.
        initForecastLoading();
        if (window.__forecastActionsBound) return;
        window.__forecastActionsBound = true;
        const previewBtn = document.getElementById('previewImportBtn');
        const previewBtnText = document.getElementById('previewBtnText');
        const confirmBtn = document.getElementById('confirmImportBtn');
        const confirmBtnText = document.getElementById('confirmBtnText');
        const cancelBtn = document.getElementById('cancelImportBtn');
        const feedback = document.getElementById('importFeedback');
        const feedbackMessage = document.getElementById('importFeedbackMessage');
        const feedbackIcon = document.getElementById('importFeedbackIcon');
        const copyFeedbackBtn = document.getElementById('copyImportFeedbackBtn');

        // Preview state stored between phases.
        let previewData = null;

        if (copyFeedbackBtn && feedbackMessage) {
            copyFeedbackBtn.addEventListener('click', function() {
                feedbackMessage.select();
                try {
                    navigator.clipboard.writeText(feedbackMessage.value);
                    const originalText = copyFeedbackBtn.querySelector('span').textContent;
                    copyFeedbackBtn.querySelector('span').textContent = 'Copied!';
                    setTimeout(function() {
                        copyFeedbackBtn.querySelector('span').textContent = originalText;
                    }, 1500);
                } catch (err) {
                    document.execCommand('copy');
                }
            });
        }

        function showImportFeedback(type, message) {
            if (!feedback || !feedbackMessage || !feedbackIcon) return;
            feedback.classList.remove('hidden', 'bg-green-50', 'border', 'border-green-200', 'text-green-800', 'bg-red-50', 'border-red-200', 'text-red-800');
            feedbackIcon.setAttribute('data-lucide', type === 'success' ? 'check-circle' : 'alert-triangle');
            if (type === 'success') {
                feedback.classList.add('bg-green-50', 'border', 'border-green-200', 'text-green-800');
                feedbackIcon.classList.add('text-green-600');
                feedbackIcon.classList.remove('text-red-600');
            } else {
                feedback.classList.add('bg-red-50', 'border', 'border-red-200', 'text-red-800');
                feedbackIcon.classList.add('text-red-600');
                feedbackIcon.classList.remove('text-green-600');
            }
            feedbackMessage.value = message;
            feedbackMessage.style.height = 'auto';
            feedbackMessage.style.height = feedbackMessage.scrollHeight + 'px';
            if (window.lucide) lucide.createIcons();
        }

        function hideImportFeedback() {
            if (feedback) feedback.classList.add('hidden');
        }

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('forecastFileInput');
        const dropZonePrompt = document.getElementById('dropZonePrompt');
        const fileSelectedPrompt = document.getElementById('fileSelectedPrompt');
        const selectedFileName = document.getElementById('selectedFileName');
        const stepFile = document.getElementById('importStepFile');
        const stepPreview = document.getElementById('importStepPreview');

        function updateDropZoneState() {
            if (!fileInput || !dropZonePrompt || !fileSelectedPrompt || !selectedFileName || !previewBtn) return;
            if (fileInput.files && fileInput.files.length > 0) {
                dropZonePrompt.classList.add('hidden');
                fileSelectedPrompt.classList.remove('hidden');
                selectedFileName.textContent = fileInput.files[0].name;
                dropZone.classList.add('border-[#2D7D46]', 'bg-[#D5E8D4]/20');
                dropZone.classList.remove('border-[#D9D9D9]');
                previewBtn.disabled = false;
            } else {
                dropZonePrompt.classList.remove('hidden');
                fileSelectedPrompt.classList.add('hidden');
                selectedFileName.textContent = 'No file selected';
                dropZone.classList.remove('border-[#2D7D46]', 'bg-[#D5E8D4]/20');
                dropZone.classList.add('border-[#D9D9D9]');
                previewBtn.disabled = true;
            }
            if (window.lucide) lucide.createIcons();
        }

        if (fileInput) {
            fileInput.addEventListener('change', updateDropZoneState);
        }

        if (dropZone) {
            ['dragenter', 'dragover'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('border-[#2D7D46]', 'bg-[#F5F6F8]');
                });
            });
            ['dragleave', 'drop'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('border-[#2D7D46]', 'bg-[#F5F6F8]');
                });
            });
            dropZone.addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                if (files.length && fileInput) {
                    fileInput.files = files;
                    updateDropZoneState();
                }
            });
        }

        // ── Phase 1: Preview ──
        if (previewBtn && fileInput) {
            previewBtn.addEventListener('click', function() {
                hideImportFeedback();
                previewBtn.disabled = true;
                if (previewBtnText) previewBtnText.textContent = 'Analyzing...';

                const formData = new FormData();
                formData.append('forecast_file', fileInput.files[0]);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                const xhr = new XMLHttpRequest();
                xhr.addEventListener('load', function() {
                    previewBtn.disabled = false;
                    if (previewBtnText) previewBtnText.textContent = 'Preview Import';

                    let response = {};
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        showImportFeedback('error', 'Unexpected server response.');
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 300 && response.temp_path) {
                        // Show preview step.
                        previewData = response;
                        document.getElementById('previewTotalRows').textContent = response.total_rows;
                        document.getElementById('previewValidRows').textContent = response.valid_rows;
                        document.getElementById('previewInvalidRows').textContent = response.invalid_count;

                        if (response.date_range) {
                            document.getElementById('previewDateRange').textContent =
                                response.date_range.start + ' to ' + response.date_range.end;
                        } else {
                            document.getElementById('previewDateRange').textContent = '—';
                        }

                        // Invalid rows detail.
                        const invalidDetail = document.getElementById('previewInvalidDetail');
                        const invalidTable = document.getElementById('previewInvalidRowsTable');
                        if (response.invalid_rows && response.invalid_rows.length > 0) {
                            invalidTable.innerHTML = '';
                            response.invalid_rows.forEach(function(row) {
                                const tr = document.createElement('tr');
                                tr.className = 'bg-amber-50/50';
                                tr.innerHTML =
                                    '<td class="px-4 py-1.5 font-medium text-amber-900">' + row.row + '</td>' +
                                    '<td class="px-4 py-1.5 text-amber-700">' + row.reason + '</td>';
                                invalidTable.appendChild(tr);
                            });
                            invalidDetail.classList.remove('hidden');
                        } else {
                            invalidDetail.classList.add('hidden');
                        }

                        // Missing columns warning.
                        const missingColsEl = document.getElementById('previewMissingCols');
                        const missingColsMsg = document.getElementById('previewMissingColsMessage');
                        if (response.missing_columns && response.missing_columns.length > 0) {
                            missingColsMsg.textContent = 'The file is missing column' +
                                (response.missing_columns.length > 1 ? 's' : '') + ': ' +
                                response.missing_columns.join(', ') +
                                '. All rows will be skipped.';
                            missingColsEl.classList.remove('hidden');
                        } else {
                            missingColsEl.classList.add('hidden');
                        }

                        // Disable confirm if no valid rows.
                        if (confirmBtn) confirmBtn.disabled = response.valid_rows === 0;

                        if (stepFile) stepFile.classList.add('hidden');
                        if (stepPreview) stepPreview.classList.remove('hidden');
                        if (window.lucide) lucide.createIcons();
                        return;
                    }

                    let message = response.message || 'Preview failed. Please check the file.';
                    if (response.errors && typeof response.errors === 'object') {
                        message = Object.values(response.errors).flat().join('\n');
                    }
                    showImportFeedback('error', message);
                });

                xhr.addEventListener('error', function() {
                    previewBtn.disabled = false;
                    if (previewBtnText) previewBtnText.textContent = 'Preview Import';
                    showImportFeedback('error', 'Preview failed. Please try again.');
                });

                xhr.open('POST', '{{ route("forecast.import.preview") }}');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(formData);
            });
        }

        // ── Phase 2: Confirm ──
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (!previewData || !previewData.temp_path) return;
                hideImportFeedback();
                confirmBtn.disabled = true;
                if (confirmBtnText) confirmBtnText.textContent = 'Importing...';

                const xhr = new XMLHttpRequest();
                xhr.addEventListener('load', function() {
                    confirmBtn.disabled = false;
                    if (confirmBtnText) confirmBtnText.textContent = 'Confirm Import';

                    let response = {};
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        showImportFeedback('error', 'Unexpected server response.');
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 300 && response.success) {
                        window.location.reload();
                        return;
                    }

                    let message = response.message || 'Import failed.';
                    if (response.errors && typeof response.errors === 'object') {
                        message = Object.values(response.errors).flat().join('\n');
                    }
                    showImportFeedback('error', message);
                    // Keep the preview visible on failure so the user sees the error.
                    // Re-enable the confirm button so they can retry.
                    if (confirmBtn) confirmBtn.disabled = false;
                    if (confirmBtnText) confirmBtnText.textContent = 'Confirm Import';
                });

                xhr.addEventListener('error', function() {
                    confirmBtn.disabled = false;
                    if (confirmBtnText) confirmBtnText.textContent = 'Confirm Import';
                    showImportFeedback('error', 'Import failed. Please try again.');
                });

                xhr.open('POST', '{{ route("forecast.import.confirm") }}');
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(JSON.stringify({
                    temp_path: previewData.temp_path,
                    source_file: previewData.source_file,
                    _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                }));
            });
        }

        // ── Cancel: reset to file selection ──
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (stepFile) stepFile.classList.remove('hidden');
                if (stepPreview) stepPreview.classList.add('hidden');
                previewData = null;
                if (fileInput) fileInput.value = '';
                updateDropZoneState();
                hideImportFeedback();
            });
        }

        // ── FAB & Import Modal ──
        const fabToggle = document.getElementById('fabToggle');
        const fabMenu = document.getElementById('fabMenu');
        const fabIcon = document.getElementById('fabIcon');
        const importModal = document.getElementById('importModal');
        const fabImportBtn = document.getElementById('fabImportBtn');
        const closeImportModalBtn = document.getElementById('closeImportModal');
        const downloadTemplateModal = document.getElementById('downloadTemplateModal');
        const fabDownloadBtn = document.getElementById('fabDownloadBtn');
        const closeDownloadTemplateModalBtn = document.getElementById('closeDownloadTemplateModal');
        const fabInputRecordsBtn = document.getElementById('fabInputRecordsBtn');
        const inputRecordsModal = document.getElementById('inputRecordsModal');
        const closeInputRecordsBtn = document.getElementById('closeInputRecords');
        const inputRecordsContent = document.getElementById('inputRecordsContent');

        function openImportModal() {
            if (importModal) {
                importModal.style.display = 'flex';
                // Reset import modal to file selection step.
                const stepFile = document.getElementById('importStepFile');
                const stepPreview = document.getElementById('importStepPreview');
                const fileInput = document.getElementById('forecastFileInput');
                if (stepFile) stepFile.classList.remove('hidden');
                if (stepPreview) stepPreview.classList.add('hidden');
                if (fileInput) fileInput.value = '';
                const dropZonePrompt = document.getElementById('dropZonePrompt');
                const fileSelectedPrompt = document.getElementById('fileSelectedPrompt');
                const selectedFileName = document.getElementById('selectedFileName');
                const dropZone = document.getElementById('dropZone');
                const previewBtn = document.getElementById('previewImportBtn');
                if (dropZonePrompt) dropZonePrompt.classList.remove('hidden');
                if (fileSelectedPrompt) fileSelectedPrompt.classList.add('hidden');
                if (selectedFileName) selectedFileName.textContent = 'No file selected';
                if (dropZone) {
                    dropZone.classList.remove('border-[#2D7D46]', 'bg-[#D5E8D4]/20');
                    dropZone.classList.add('border-[#D9D9D9]');
                }
                if (previewBtn) previewBtn.disabled = true;
                const feedback = document.getElementById('importFeedback');
                if (feedback) feedback.classList.add('hidden');
                if (window.lucide) lucide.createIcons();
            }
        }

        function closeImportModalFn() {
            if (importModal) importModal.style.display = 'none';
        }

        function openDownloadTemplateModal() {
            if (downloadTemplateModal) {
                downloadTemplateModal.style.display = 'flex';
                if (window.lucide) lucide.createIcons();
            }
        }

        function closeDownloadTemplateModalFn() {
            if (downloadTemplateModal) downloadTemplateModal.style.display = 'none';
        }

        if (fabToggle && fabMenu) {
            fabToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleFab();
            });

            fabMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        if (fabImportBtn) {
            fabImportBtn.addEventListener('click', function() {
                openImportModal();
                toggleFab();
            });
        }

        if (fabDownloadBtn) {
            fabDownloadBtn.addEventListener('click', function() {
                openDownloadTemplateModal();
                toggleFab();
            });
        }

        if (closeImportModalBtn) {
            closeImportModalBtn.addEventListener('click', closeImportModalFn);
        }

        if (closeDownloadTemplateModalBtn) {
            closeDownloadTemplateModalBtn.addEventListener('click', closeDownloadTemplateModalFn);
        }

        // "View input records status" — fetch + render the records table.
        function openInputRecordsFn() {
            if (!inputRecordsModal) return;
            inputRecordsModal.style.display = 'flex';
            if (inputRecordsContent) {
                inputRecordsContent.innerHTML = '<div class="text-center py-8 text-[#6B7280] text-sm"><i data-lucide="loader" class="w-6 h-6 mx-auto mb-2 animate-spin" style="color:#0075de;"></i>Loading records…</div>';
                if (window.lucide) lucide.createIcons();
            }
            fetch('{{ route("forecast.input-records") }}', { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    renderInputRecords(data);
                })
                .catch(function() {
                    if (inputRecordsContent) {
                        inputRecordsContent.innerHTML = '<div class="text-center py-8 text-[#9b1c24] text-sm">Failed to load records.</div>';
                    }
                });
        }

        function closeInputRecordsFn() {
            if (inputRecordsModal) inputRecordsModal.style.display = 'none';
        }

        function renderInputRecords(data) {
            if (!inputRecordsContent) return;
            var s = data.summary || {};
            var rows = data.rows || [];

            var html = '<div class="mb-4 p-3 rounded-lg flex items-start gap-2 text-xs" style="background-color:#eef2fb; color:#002D5E; border:1px solid #d6e0f2;">'
                + '<i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>'
                + '<span>The system fills this table automatically each day (at the day-reset time). Every recorded production day is added so the forecast has enough history.</span>'
                + '</div>';

            html += '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-center">'
                + '<div class="bg-[#F5F6F8] rounded-lg p-3"><div class="text-lg font-bold text-[#002D5E]">' + (s.total_records ?? 0) + '</div><div class="text-[10px] uppercase tracking-wider text-[#6B7280]">Records</div></div>'
                + '<div class="bg-[#F5F6F8] rounded-lg p-3"><div class="text-lg font-bold text-[#002D5E]">' + (s.distinct_days ?? 0) + '</div><div class="text-[10px] uppercase tracking-wider text-[#6B7280]">Days</div></div>'
                + '<div class="bg-[#F5F6F8] rounded-lg p-3"><div class="text-sm font-bold text-[#002D5E]">' + (s.min_date ?? '—') + '</div><div class="text-[10px] uppercase tracking-wider text-[#6B7280]">From</div></div>'
                + '<div class="bg-[#F5F6F8] rounded-lg p-3"><div class="text-sm font-bold text-[#002D5E]">' + (s.max_date ?? '—') + '</div><div class="text-[10px] uppercase tracking-wider text-[#6B7280]">To</div></div>'
                + '</div>';

            if (!rows.length) {
                html += '<div class="text-center py-10 text-sm text-[#6B7280]">No forecast input records yet. Log eggs daily or import a sheet.</div>';
                inputRecordsContent.innerHTML = html;
                return;
            }

            // Scrollable table showing every column of forecast_input_records.
            html += '<div class="overflow-auto border rounded-lg max-h-[55vh]">'
                + '<table class="w-full text-xs whitespace-nowrap">'
                + '<thead class="sticky top-0"><tr class="bg-[#F5F6F8] text-[#6B7280] uppercase tracking-wider">'
                + '<th class="text-left p-2.5 sticky left-0 bg-[#F5F6F8]">Date</th>'
                + '<th class="text-left p-2.5">Cage</th><th class="text-left p-2.5">Breed</th><th class="text-right p-2.5">Flk Age</th>'
                + '<th class="text-right p-2.5">Hens</th><th class="text-right p-2.5">Eggs</th><th class="text-right p-2.5">Temp</th><th class="text-right p-2.5">Hum</th>'
                + '<th class="text-right p-2.5">CP%</th><th class="text-right p-2.5">Feed</th><th class="text-right p-2.5">Mort</th>'
                + '<th class="text-left p-2.5">Source</th><th class="text-left p-2.5">Updated</th><th class="text-right p-2.5">ID</th>'
                + '</tr></thead><tbody>';
            rows.forEach(function(r) {
                html += '<tr class="border-t border-[#F0F0F0]">'
                    + '<td class="p-2.5 font-mono text-[#333] sticky left-0 bg-white">' + (r.date || '—') + '</td>'
                    + '<td class="p-2.5 text-[#333]">' + (r.cage_code || '—') + '</td>'
                    + '<td class="p-2.5 text-[#333]">' + (r.breed || '—') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.flock_age_weeks ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.hen_count ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.egg_count ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.temperature_c ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.humidity_percent ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.crude_protein_percent ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.feed_consumed_kg ?? '') + '</td>'
                    + '<td class="p-2.5 text-right text-[#333]">' + (r.mortality_count ?? '') + '</td>'
                    + '<td class="p-2.5 text-[#333]">' + (r.source_file || '—') + '</td>'
                    + '<td class="p-2.5 text-[#333]">' + (r.updated_at || '—') + '</td>'
                    + '<td class="p-2.5 text-right text-[#6B7280]">' + (r.id ?? '') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table></div>';
            inputRecordsContent.innerHTML = html;
        }

        if (fabInputRecordsBtn) {
            fabInputRecordsBtn.addEventListener('click', function() {
                openInputRecordsFn();
                toggleFab();
            });
        }
        if (closeInputRecordsBtn) {
            closeInputRecordsBtn.addEventListener('click', closeInputRecordsFn);
        }
        if (inputRecordsModal) {
            inputRecordsModal.addEventListener('click', function(e) {
                if (e.target === inputRecordsModal) closeInputRecordsFn();
            });
        }

        // Buttons on the insufficient-data lock popup.
        var lockDownloadBtn = document.getElementById('lockDownloadBtn');
        var lockInputRecordsBtn = document.getElementById('lockInputRecordsBtn');
        var lockImportBtn = document.getElementById('lockImportBtn');
        if (lockDownloadBtn) {
            lockDownloadBtn.addEventListener('click', function() { openDownloadTemplateModal(); });
        }
        if (lockInputRecordsBtn) {
            lockInputRecordsBtn.addEventListener('click', function() { openInputRecordsFn(); });
        }
        if (lockImportBtn) {
            lockImportBtn.addEventListener('click', function() { openImportModal(); });
        }

        if (importModal) {
            importModal.addEventListener('click', function(e) {
                if (e.target === importModal) {
                    closeImportModalFn();
                }
            });
        }

        if (downloadTemplateModal) {
            downloadTemplateModal.addEventListener('click', function(e) {
                if (e.target === downloadTemplateModal) {
                    closeDownloadTemplateModalFn();
                }
            });
        }

        // ── Export Forecast Modal ──
        const exportModal = document.getElementById('exportForecastModal');
        const fabExportBtn = document.getElementById('fabExportBtn');
        const closeExportModalBtn = document.getElementById('closeExportForecastModal');
        const exportOverlay = document.getElementById('forecastExportLoadingOverlay');

        function openExportModal() {
            if (exportModal) {
                exportModal.style.display = 'flex';
                if (window.lucide) lucide.createIcons();
            }
        }

        function closeExportModalFn() {
            if (exportModal) exportModal.style.display = 'none';
        }

        if (fabExportBtn) {
            fabExportBtn.addEventListener('click', function() {
                openExportModal();
                toggleFab();
            });
        }

        if (closeExportModalBtn) {
            closeExportModalBtn.addEventListener('click', closeExportModalFn);
        }

        if (exportModal) {
            exportModal.addEventListener('click', function(e) {
                if (e.target === exportModal) closeExportModalFn();
            });
        }

        function showExportLoading() {
            if (exportOverlay) exportOverlay.style.display = 'flex';
        }

        function hideExportLoading() {
            if (exportOverlay) exportOverlay.style.display = 'none';
        }

        function exportForecast(format) {
            closeExportModalFn();
            showExportLoading();

            var form = document.getElementById('forecastForm');
            var scope = form ? (form.querySelector('[name="scope"]') ? form.querySelector('[name="scope"]').value : 'cage') : 'cage';
            var cage = form ? (form.querySelector('[name="cage"]') ? form.querySelector('[name="cage"]').value : '') : '';
            var breed = form ? (form.querySelector('[name="breed"]') ? form.querySelector('[name="breed"]').value : '') : '';
            var horizon = form ? (form.querySelector('[name="horizon"]') ? form.querySelector('[name="horizon"]').value : '7') : '7';

            var extMap = { csv: 'csv', excel: 'xlsx', pdf: 'pdf' };
            var extension = extMap[format] || format;

            var chartCanvas = document.querySelector('#forecastChart');
            var chartImage = null;

            if (format !== 'csv' && chartCanvas && typeof chartCanvas.toDataURL === 'function') {
                chartImage = chartCanvas.toDataURL('image/png');
            }

            var params = new URLSearchParams();
            params.set('scope', scope);
            params.set('horizon', horizon);
            if (cage) params.set('cage', cage);
            if (breed) params.set('breed', breed);

            fetch('/forecast/' + format, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/octet-stream'
                },
                body: JSON.stringify({
                    scope: scope,
                    cage: cage,
                    breed: breed,
                    horizon: horizon,
                    chart_image: chartImage
                })
            })
            .then(function(response) {
                if (!response.ok) {
                    // The server returns a JSON error (e.g. "no forecast generated
                    // yet") for a non-2xx here — surface that instead of a blob
                    // download. Falls back to a generic message if the body isn't
                    // JSON for some other reason (network error page, etc.).
                    return response.json()
                        .catch(function() { return {}; })
                        .then(function(body) {
                            throw new Error(body.message || 'Export failed (' + response.status + ').');
                        });
                }
                return response.blob();
            })
            .then(function(blob) {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'forecast-export-' + format + '-' + new Date().toISOString().slice(0,10) + '.' + extension;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            })
            .catch(function(err) {
                console.error('Forecast export error:', err);
                alert(err.message || 'Export failed. Please try again.');
            })
            .finally(function() {
                hideExportLoading();
            });
        }

        document.querySelectorAll('.export-forecast-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var format = this.getAttribute('data-export-format');
                exportForecast(format);
            });
        });
    });

    document.addEventListener('turbo:frame-load', function(e) {
        if (e.target.id === 'forecast-workspace') {
            initForecastLoading();
        }
        if (e.target.id === 'production-calendar') {
            if (window.lucide) lucide.createIcons();
        }
    });

    // ── Prevent unnecessary frame navigation when clicking the already-active scope ──
    document.addEventListener('click', function(e) {
        var link = e.target.closest('[data-turbo-frame="forecast-workspace"][href]');
        if (!link) return;
        var linkUrl = new URL(link.href, window.location.origin);
        var currentUrl = new URL(window.location.href);
        if (linkUrl.pathname + linkUrl.search === currentUrl.pathname + currentUrl.search) {
            e.preventDefault();
        }
    });
})();
</script>
@endpush
