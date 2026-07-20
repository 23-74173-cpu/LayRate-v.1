@extends('layouts.app')
@section('title', 'Forecast')

@section('content')
<div class="space-y-5">

    <x-page-header title="Forecast" subtitle="Project egg production based on historical egg count trends" />

    @if(!($hasEnoughData ?? true))
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
        <div>
            <div class="text-sm font-medium text-amber-800">Insufficient forecast data</div>
            <div class="text-sm text-amber-700 mt-1">
                The forecast input table must contain at least 90 days of production records before generating a forecast.
                Please download the forecast input sheet, fill it out, and import the data.
            </div>
        </div>
    </div>
    @endif

    {{-- ── Forecast Workspace (Inputs + Chart + Metrics) ── --}}
    @include('forecast._workspace')

    {{-- ── Production Calendar ── --}}
    @include('forecast._calendar')

{{-- Download Template Modal --}}
<div id="downloadTemplateModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
<div id="forecastLoadingOverlay" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
<div class="fixed bottom-6 right-3 z-40 flex flex-col items-end gap-3">
    {{-- FAB Menu --}}
    <div id="fabMenu" class="flex flex-col items-end gap-2 mb-1 mr-2 transition-all duration-200 opacity-0 invisible translate-y-4">
        <button type="button" id="fabDownloadBtn" class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Download input sheet</span>
            <div class="w-8 h-8 rounded-full bg-[#002D5E]/10 flex items-center justify-center">
                <i data-lucide="download" class="w-4 h-4 text-[#002D5E]"></i>
            </div>
        </button>
        <button type="button" id="fabImportBtn" class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Import production data</span>
            <div class="w-8 h-8 rounded-full bg-[#2D7D46]/10 flex items-center justify-center">
                <i data-lucide="upload" class="w-4 h-4 text-[#2D7D46]"></i>
            </div>
        </button>
    </div>

    {{-- FAB Toggle --}}
    <button type="button" id="fabToggle"
        class="w-16 h-12 rounded-full bg-surface text-ink border border-hairline shadow-soft hover:bg-canvas-soft transition-colors flex items-center justify-center flex-shrink-0"
        aria-label="Open menu" aria-expanded="false">
        <i data-lucide="plus" id="fabIcon" class="w-6 h-6 transition-transform duration-200 ease-out"></i>
    </button>
</div>

{{-- Import Modal --}}
<div id="importModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
                Import a filled <strong>.xlsx</strong> forecast input sheet. At minimum the sheet must include <strong>Date</strong> and <strong>Cage_Code</strong>.
            </p>

            {{-- Inline import feedback messages --}}
            <div id="importFeedback" class="hidden mb-3 rounded-lg p-3 text-sm">
                <div class="flex items-start gap-2">
                    <i data-lucide="" id="importFeedbackIcon" class="w-4 h-4 shrink-0 mt-0.5"></i>
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
            <form method="POST" action="{{ route('forecast.import') }}" id="forecastImportForm" enctype="multipart/form-data" data-turbo="false">
                @csrf
                <div id="dropZone" class="group relative border-2 border-dashed border-[#D9D9D9] rounded-lg p-5 hover:border-[#2D7D46] hover:bg-[#F5F6F8] transition-all cursor-pointer text-center mb-3">
                    <input type="file" name="forecast_file" id="forecastFileInput" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
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
                <x-button type="submit" id="importForecastBtn" class="w-full py-3 shadow-md" disabled>
                    <i data-lucide="upload" class="w-5 h-5 shrink-0"></i>
                    <span id="importBtnText">Start Importing</span>
                </x-button>
            </form>
            @endcan
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    if (window.__layrateForecastInitialized) return;
    window.__layrateForecastInitialized = true;

    let currentFabMenu = null;
    let currentFabIcon = null;

    function toggleFab() {
        if (!currentFabMenu) return;
        const isOpen = !currentFabMenu.classList.contains('invisible');
        const fabToggle = document.getElementById('fabToggle');

        if (isOpen) {
            currentFabMenu.classList.add('invisible', 'opacity-0', 'translate-y-4');
            currentFabMenu.classList.remove('opacity-100', 'translate-y-0');
            if (currentFabIcon) currentFabIcon.style.transform = 'rotate(0deg)';

            if (fabToggle) {
                fabToggle.setAttribute('aria-expanded', 'false');
                fabToggle.setAttribute('aria-label', 'Open menu');
            }
        } else {
            currentFabMenu.classList.remove('invisible', 'opacity-0', 'translate-y-4');
            currentFabMenu.classList.add('opacity-100', 'translate-y-0');
            if (currentFabIcon) currentFabIcon.style.transform = 'rotate(45deg)';

            if (fabToggle) {
                fabToggle.setAttribute('aria-expanded', 'true');
                fabToggle.setAttribute('aria-label', 'Close menu');
            }
        }
    }

    document.addEventListener('click', function() {
        if (currentFabMenu && !currentFabMenu.classList.contains('invisible')) {
            toggleFab();
        }
    });

    function initForecastLoading() {
        const STORAGE_KEYS = {
            startTime: 'layrate_forecast_start_time',
            expectedDuration: 'layrate_forecast_expected_duration_ms',
        };

        const DEFAULT_DURATIONS = {
            cage: { 7: 5000, 14: 8000, 30: 12000 },
            breed: { 7: 6000, 14: 10000, 30: 15000 },
            farm: { 7: 8000, 14: 14000, 30: 20000 },
        };

        const MIN_DURATION = 3000;
        const MAX_DURATION = 280000;

        function resolveExpectedDuration(scope, horizon) {
            const saved = localStorage.getItem(STORAGE_KEYS.expectedDuration);
            if (saved) {
                const parsed = parseInt(saved, 10);
                if (!isNaN(parsed) && parsed > 0) {
                    return Math.max(MIN_DURATION, Math.min(MAX_DURATION, parsed));
                }
            }

            const scopeMap = DEFAULT_DURATIONS[scope] || DEFAULT_DURATIONS.cage;
            return scopeMap[horizon] || scopeMap[7] || MIN_DURATION;
        }

        function recordActualDuration() {
            const start = sessionStorage.getItem(STORAGE_KEYS.startTime);
            if (!start) return;

            const actual = Date.now() - parseInt(start, 10);
            sessionStorage.removeItem(STORAGE_KEYS.startTime);

            if (actual > 1000) {
                localStorage.setItem(STORAGE_KEYS.expectedDuration, actual);
            }
        }

        recordActualDuration();

        const form = document.getElementById('forecastForm');
        const overlay = document.getElementById('forecastLoadingOverlay');
        const progressBar = document.getElementById('forecastProgressBar');
        const progressText = document.getElementById('forecastProgressText');
        const statusText = document.getElementById('forecastStatusText');
        const btn = document.getElementById('generateForecastBtn');
        const btnText = document.getElementById('btnText');

        if (!form || !overlay || !btn) return;

        // Remove a previously attached handler so we never stack listeners when the frame reloads.
        if (form._forecastSubmitHandler) {
            form.removeEventListener('submit', form._forecastSubmitHandler);
        }

        let isSubmitting = false;

        const handler = function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            isSubmitting = true;
            e.preventDefault();

            const scopeInput = form.querySelector('input[name="scope"]');
            const horizonInput = form.querySelector('input[name="horizon"]:checked');
            const scope = scopeInput ? scopeInput.value : 'cage';
            const horizon = horizonInput ? parseInt(horizonInput.value, 10) : 7;
            const expectedDuration = resolveExpectedDuration(scope, horizon);

            overlay.classList.remove('hidden');
            btn.disabled = true;
            if (btnText) {
                btnText.textContent = 'Generating...';
            }

            sessionStorage.setItem(STORAGE_KEYS.startTime, Date.now());

            progressBar.style.width = '0%';
            progressText.textContent = '0%';
            if (statusText) {
                statusText.textContent = 'Loading historical data...';
            }

            const statusMessages = [
                'Loading historical data...',
                'Training SARIMA model...',
                'Training XGBoost model...',
                'Evaluating model performance...',
                'Generating predictions...',
                'Saving forecast results...',
            ];

            const startTime = Date.now();

            function updateProgress() {
                const elapsed = Date.now() - startTime;
                const ratio = Math.min(elapsed / expectedDuration, 1);
                const eased = 1 - Math.pow(1 - ratio, 3);
                const progress = Math.min(95, eased * 95);

                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';

                const messageIndex = Math.min(
                    statusMessages.length - 1,
                    Math.floor(ratio * statusMessages.length)
                );
                if (statusText) {
                    statusText.textContent = statusMessages[messageIndex];
                }

                if (progress < 95) {
                    requestAnimationFrame(updateProgress);
                }
            }

            requestAnimationFrame(updateProgress);

            requestAnimationFrame(function() {
                setTimeout(function() {
                    form.submit();
                }, 50);
            });
        };

        form._forecastSubmitHandler = handler;
        form.addEventListener('submit', handler);
    }

    document.addEventListener('turbo:load', function() {
        const form = document.getElementById('forecastImportForm');
        const btn = document.getElementById('importForecastBtn');
        const btnText = document.getElementById('importBtnText');
        const feedback = document.getElementById('importFeedback');
        const feedbackMessage = document.getElementById('importFeedbackMessage');
        const feedbackIcon = document.getElementById('importFeedbackIcon');
        const copyFeedbackBtn = document.getElementById('copyImportFeedbackBtn');

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

        function updateDropZoneState() {
            if (!fileInput || !dropZonePrompt || !fileSelectedPrompt || !selectedFileName || !btn) return;
            if (fileInput.files && fileInput.files.length > 0) {
                dropZonePrompt.classList.add('hidden');
                fileSelectedPrompt.classList.remove('hidden');
                selectedFileName.textContent = fileInput.files[0].name;
                dropZone.classList.add('border-[#2D7D46]', 'bg-[#D5E8D4]/20');
                dropZone.classList.remove('border-[#D9D9D9]');
                btn.disabled = false;
            } else {
                dropZonePrompt.classList.remove('hidden');
                fileSelectedPrompt.classList.add('hidden');
                selectedFileName.textContent = 'No file selected';
                dropZone.classList.remove('border-[#2D7D46]', 'bg-[#D5E8D4]/20');
                dropZone.classList.add('border-[#D9D9D9]');
                btn.disabled = true;
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

        if (form && btn) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                hideImportFeedback();
                btn.disabled = true;
                if (btnText) {
                    btnText.textContent = 'Importing...';
                }

                const xhr = new XMLHttpRequest();
                const formData = new FormData(form);

                xhr.addEventListener('load', function() {
                    btn.disabled = false;
                    if (btnText) {
                        btnText.textContent = 'Start Importing';
                    }

                    let response = {};
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        response = { success: false, message: 'Unexpected server response.\n\n' + xhr.responseText };
                    }

                    if (xhr.status >= 200 && xhr.status < 300 && response.success) {
                        window.location.reload();
                        return;
                    }

                    let message = response.message || 'Import failed. Please check the file and try again.';
                    if (response.errors && typeof response.errors === 'object') {
                        message = Object.values(response.errors).flat().join('\n');
                    }
                    showImportFeedback('error', message);
                });

                xhr.addEventListener('error', function() {
                    btn.disabled = false;
                    if (btnText) {
                        btnText.textContent = 'Start Importing';
                    }
                    showImportFeedback('error', 'Import failed. Please check the file and try again.');
                });

                xhr.open('POST', form.action);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(formData);
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

        currentFabMenu = fabMenu;
        currentFabIcon = fabIcon;

        function openImportModal() {
            if (importModal) {
                importModal.classList.remove('hidden');
                if (window.lucide) lucide.createIcons();
            }
        }

        function closeImportModalFn() {
            if (importModal) importModal.classList.add('hidden');
        }

        function openDownloadTemplateModal() {
            if (downloadTemplateModal) {
                downloadTemplateModal.classList.remove('hidden');
                if (window.lucide) lucide.createIcons();
            }
        }

        function closeDownloadTemplateModalFn() {
            if (downloadTemplateModal) downloadTemplateModal.classList.add('hidden');
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

        initForecastLoading();
    });

    document.addEventListener('turbo:frame-load', function(e) {
        if (e.target.id === 'forecast-workspace') {
            initForecastLoading();
        }
    });
})();
</script>
@endpush
