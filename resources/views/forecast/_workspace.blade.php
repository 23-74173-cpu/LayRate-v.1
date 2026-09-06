@php
    $scopeLabel = match($scope) {
        'farm' => 'Whole Farm',
        'breed' => $breed ?? '',
        default => $cageCode,
    };
    $showForecast = session('forecast_generated', false);
@endphp

<turbo-frame id="forecast-workspace">
<div class="space-y-5">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- ── Inputs Panel ── --}}
        <x-card padding="p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-4">Forecast Inputs</div>
            <form method="POST" action="{{ route('forecast.generate') }}" id="forecastForm" data-turbo="false">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}" id="formScope">
                <input type="hidden" name="cage" value="{{ $cageCode }}" id="formCage">
                <input type="hidden" name="breed" value="{{ $breed }}" id="formBreed">

                <label class="block text-sm text-[#333333] mb-2">Scope</label>
                <div class="flex flex-col gap-2 mb-4">
                    <a href="{{ route('forecast', ['scope'=>'farm','horizon'=>$horizon]) }}" data-forecast-scope="farm" data-turbo-frame="forecast-workspace"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'farm' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="globe" class="w-4 h-4 shrink-0"></i> Whole Farm
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'cage','cage'=>$cageCode,'horizon'=>$horizon]) }}" data-forecast-scope="cage" data-turbo-frame="forecast-workspace"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'cage' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="box" class="w-4 h-4 shrink-0"></i> Per Cage
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'breed','breed'=>$allBreeds->first() ?? 'ISA Brown','horizon'=>$horizon]) }}" data-forecast-scope="breed" data-turbo-frame="forecast-workspace"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'breed' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="bird" class="w-4 h-4 shrink-0"></i> Per Breed
                    </a>
                </div>

                <div id="cageScopeBlock" {!! $scope !== 'cage' ? 'style="display:none"' : '' !!}>
                    <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                    <select name="cage" id="cageSelect" onchange="window.refreshForecastWorkspace && refreshForecastWorkspace()"
                            class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                        @foreach($allCages as $c)
                        <option value="{{ $c }}" {{ $c === $cageCode ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="forecast-target-label font-medium text-[#333333]">{{ $cageCode }}</span></p>
                </div>

                <div id="breedScopeBlock" {!! $scope !== 'breed' ? 'style="display:none"' : '' !!}>
                    <label class="block text-sm text-[#333333] mb-2">Select Breed</label>
                    <select name="breed" id="breedSelect" onchange="window.refreshForecastWorkspace && refreshForecastWorkspace()"
                            class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                        @foreach($allBreeds as $b)
                        <option value="{{ $b }}" {{ $breed === $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="forecast-target-label font-medium text-[#002D5E]">{{ $breed }}</span></p>
                </div>

                <div id="farmScopeBlock" {!! $scope !== 'farm' ? 'style="display:none"' : '' !!}>
                    <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="forecast-target-label font-medium text-[#333333]">Whole Farm</span></p>
                </div>

                <label class="block text-sm text-[#333333] mb-2">Forecast horizon</label>
                <div class="flex gap-4 mb-5">
                    @foreach([7,14,30] as $h)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="radio" name="horizon" value="{{ $h }}" {{ $horizon == $h ? 'checked' : '' }} class="accent-[#002D5E]" onchange="window.refreshForecastWorkspace && refreshForecastWorkspace()">
                        {{ $h }} days
                    </label>
                    @endforeach
                </div>

                @can('admin')
                <button type="submit" id="generateForecastBtn" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <span id="btnText">Generate Forecast</span>
                </button>
                @endcan
            </form>
        </x-card>

        {{-- ── Production Calendar ── --}}
        <div class="xl:col-span-2">
            @include('forecast._calendar')
        </div>
    </div>

    {{-- ── Model Metrics ── --}}
    @php
        $metrics = $metrics ?? [];
        $recommended = $recommendedModel ?? null;
        $sarima = $metrics['sarima'] ?? null;
        $xgboost = $metrics['xgboost'] ?? null;

        $maeWinner = null;
        $rmseWinner = null;
        $mapeWinner = null;
        if ($sarima && $xgboost) {
            $maeWinner = ($sarima['MAE'] ?? PHP_FLOAT_MAX) <= ($xgboost['MAE'] ?? PHP_FLOAT_MAX) ? 'SARIMA' : 'XGBoost';
            $rmseWinner = ($sarima['RMSE'] ?? PHP_FLOAT_MAX) <= ($xgboost['RMSE'] ?? PHP_FLOAT_MAX) ? 'SARIMA' : 'XGBoost';
            $mapeWinner = ($sarima['MAPE'] ?? PHP_FLOAT_MAX) <= ($xgboost['MAPE'] ?? PHP_FLOAT_MAX) ? 'SARIMA' : 'XGBoost';
        }
        $usedMetrics = match($recommended) {
            'SARIMA'  => $sarima,
            'XGBoost' => $xgboost,
            default   => $sarima ?? $xgboost,
        };
        $avgEggs = round($forecasts->avg('predicted_egg_count') ?? 0);
        $showSummaryBlock = $showForecast && $forecasts->count() > 0;
        $showMetricsBlock = $showForecast && (!empty($metrics) || $recommendedModel);
    @endphp

    {{-- ── Forecast Summary ── --}}
    <div id="forecastSummary" class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 rounded-lg bg-[#F5F6F8] border border-[#D9D9D9] text-sm mb-5 {{ $showSummaryBlock ? '' : 'hidden' }}">
        @if($showSummaryBlock)
        <span class="text-[#6B7280]">Forecast avg for <strong>{{ $scopeLabel }}</strong>:</span>
        <span class="text-2xl font-bold text-[#333333]">{{ number_format($avgEggs) }}</span>
        <span class="text-[#6B7280]">eggs/day</span>
        @if($usedMetrics)
            @if(isset($usedMetrics['MAE']))
            <span class="text-[#6B7280]">±{{ number_format($usedMetrics['MAE'], 1) }} MAE</span>
            @endif
            @if(isset($usedMetrics['MAPE']) && $usedMetrics['MAPE'] !== null)
            <span class="text-[#6B7280]">{{ number_format($usedMetrics['MAPE'], 1) }}% MAPE</span>
            @endif
            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-[#002D5E]/10 text-[#002D5E] font-medium">
                <i data-lucide="award" class="w-3 h-3"></i>{{ $recommended }}
            </span>
        @endif
        @endif
    </div>

    <div id="modelComparison" class="bg-white rounded-lg border border-[#D9D9D9] p-4 {{ $showMetricsBlock ? '' : 'hidden' }}">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-4">Model Comparison</div>

        @if($recommended)
        <div class="flex items-center gap-3 p-4 rounded-lg bg-[#D5E8D4] text-[#1F5F35] mb-5">
            <div class="w-10 h-10 rounded-full bg-[#1F5F35]/10 flex items-center justify-center">
                <i data-lucide="award" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-[#1F5F35]/80">Suggested Model</div>
                <div class="text-lg font-semibold">{{ $recommended }}</div>
            </div>
        </div>
        @endif

        @if($sarima || $xgboost)
        <div class="overflow-hidden rounded-lg border border-[#D9D9D9]">
            <table class="w-full text-sm">
                <thead class="bg-[#F9F9F7] border-b border-[#D9D9D9]">
                    <tr>
                        <th class="text-left text-xs font-medium text-[#6B7280] px-5 py-3">Metric</th>
                        <th class="text-right text-xs font-medium text-[#6B7280] px-5 py-3">SARIMA</th>
                        <th class="text-right text-xs font-medium text-[#6B7280] px-5 py-3">XGBoost Regression</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#F0F0F0]">
                        <td class="px-5 py-3.5 text-[#333333]">MAE</td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $maeWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ $sarima ? number_format($sarima['MAE'], 2) : 'N/A' }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $maeWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ $xgboost ? number_format($xgboost['MAE'], 2) : 'N/A' }}
                        </td>
                    </tr>
                    <tr class="border-b border-[#F0F0F0]">
                        <td class="px-5 py-3.5 text-[#333333]">RMSE</td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $rmseWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ $sarima ? number_format($sarima['RMSE'], 2) : 'N/A' }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $rmseWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ $xgboost ? number_format($xgboost['RMSE'], 2) : 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3.5 text-[#333333]">MAPE</td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $mapeWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ $sarima ? number_format($sarima['MAPE'], 2).'%' : 'N/A' }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $mapeWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ $xgboost ? number_format($xgboost['MAPE'], 2).'%' : 'N/A' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-[#6B7280] mt-2">Lower values are better. Highlighted cells indicate the best performing model for each metric.</p>
        @endif
    </div>
</div>

<script>
(function() {
    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function setScopeButtonStates(scope) {
        document.querySelectorAll('[data-forecast-scope]').forEach(function(link) {
            const isActive = link.getAttribute('data-forecast-scope') === scope;
            link.classList.remove('bg-[#002D5E]', 'text-white', 'border-[#002D5E]', 'border-[#D9D9D9]', 'text-[#6B7280]');
            if (isActive) {
                link.classList.add('bg-[#002D5E]', 'text-white', 'border-[#002D5E]');
            } else {
                link.classList.add('border-[#D9D9D9]', 'text-[#6B7280]');
            }
        });
    }

    function setScopeBlocks(scope) {
        const farm  = document.getElementById('farmScopeBlock');
        const cage  = document.getElementById('cageScopeBlock');
        const breed = document.getElementById('breedScopeBlock');
        if (farm)  farm.style.display  = scope === 'farm'  ? '' : 'none';
        if (cage)  cage.style.display  = scope === 'cage'  ? '' : 'none';
        if (breed) breed.style.display = scope === 'breed' ? '' : 'none';
    }

    function updateForecastSummary(data) {
        const el = document.getElementById('forecastSummary');
        if (!el) return;
        const show = data.showForecast && Array.isArray(data.forecasts) && data.forecasts.length > 0;
        el.classList.toggle('hidden', !show);
        if (!show) return;

        const total = data.forecasts.reduce(function(sum, f) { return sum + (Number(f.egg_count) || 0); }, 0);
        const avg = Math.round(total / data.forecasts.length);
        const metrics = data.metrics || {};
        const recommended = data.recommendedModel;
        const used = recommended === 'SARIMA' ? metrics.sarima
            : recommended === 'XGBoost' ? metrics.xgboost
            : (metrics.sarima || metrics.xgboost || null);

        let html = '<span class="text-[#6B7280]">Forecast avg for <strong>' + escapeHtml(data.scopeLabel) + '</strong>:</span>';
        html += '<span class="text-2xl font-bold text-[#333333]">' + avg.toLocaleString() + '</span>';
        html += '<span class="text-[#6B7280]">eggs/day</span>';
        if (used) {
            if (typeof used.MAE !== 'undefined' && used.MAE !== null) {
                html += '<span class="text-[#6B7280]">&plusmn;' + Number(used.MAE).toFixed(1) + ' MAE</span>';
            }
            if (typeof used.MAPE !== 'undefined' && used.MAPE !== null) {
                html += '<span class="text-[#6B7280]">' + Number(used.MAPE).toFixed(1) + '% MAPE</span>';
            }
            html += '<span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-[#002D5E]/10 text-[#002D5E] font-medium">'
                + '<i data-lucide="award" class="w-3 h-3"></i>' + escapeHtml(recommended || '') + '</span>';
        }
        el.innerHTML = html;
    }

    function metricCell(value, winner, suffix) {
        const cls = winner ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]';
        const display = (value === null || value === undefined) ? 'N/A'
            : Number(value).toFixed(2) + (suffix || '');
        return '<td class="px-5 py-3.5 text-right font-mono ' + cls + '">' + display + '</td>';
    }

    function updateModelComparison(data) {
        const el = document.getElementById('modelComparison');
        if (!el) return;
        const metrics = data.metrics || {};
        const sarima = metrics.sarima || null;
        const xgboost = metrics.xgboost || null;
        const recommended = data.recommendedModel || null;
        const show = data.showForecast && (sarima || xgboost);
        el.classList.toggle('hidden', !show);
        if (!show) return;

        const num = function(v) { return (v === null || v === undefined) ? Infinity : Number(v); };
        const maeWinner  = (sarima && xgboost) ? (num(sarima.MAE)  <= num(xgboost.MAE)  ? 'SARIMA' : 'XGBoost') : null;
        const rmseWinner = (sarima && xgboost) ? (num(sarima.RMSE) <= num(xgboost.RMSE) ? 'SARIMA' : 'XGBoost') : null;
        const mapeWinner = (sarima && xgboost) ? (num(sarima.MAPE) <= num(xgboost.MAPE) ? 'SARIMA' : 'XGBoost') : null;

        let html = '';
        if (recommended) {
            html += '<div class="flex items-center gap-3 p-4 rounded-lg bg-[#D5E8D4] text-[#1F5F35] mb-5">'
                + '<div class="w-10 h-10 rounded-full bg-[#1F5F35]/10 flex items-center justify-center"><i data-lucide="award" class="w-5 h-5"></i></div>'
                + '<div><div class="text-xs font-medium uppercase tracking-wider text-[#1F5F35]/80">Suggested Model</div>'
                + '<div class="text-lg font-semibold">' + escapeHtml(recommended) + '</div></div></div>';
        }
        if (sarima || xgboost) {
            html += '<div class="overflow-hidden rounded-lg border border-[#D9D9D9]">'
                + '<table class="w-full text-sm">'
                + '<thead class="bg-[#F9F9F7] border-b border-[#D9D9D9]">'
                + '<tr><th class="text-left text-xs font-medium text-[#6B7280] px-5 py-3">Metric</th>'
                + '<th class="text-right text-xs font-medium text-[#6B7280] px-5 py-3">SARIMA</th>'
                + '<th class="text-right text-xs font-medium text-[#6B7280] px-5 py-3">XGBoost Regression</th></tr></thead><tbody>'
                + '<tr class="border-b border-[#F0F0F0]"><td class="px-5 py-3.5 text-[#333333]">MAE</td>'
                + metricCell(sarima ? sarima.MAE : null, maeWinner === 'SARIMA')
                + metricCell(xgboost ? xgboost.MAE : null, maeWinner === 'XGBoost')
                + '</tr>'
                + '<tr class="border-b border-[#F0F0F0]"><td class="px-5 py-3.5 text-[#333333]">RMSE</td>'
                + metricCell(sarima ? sarima.RMSE : null, rmseWinner === 'SARIMA')
                + metricCell(xgboost ? xgboost.RMSE : null, rmseWinner === 'XGBoost')
                + '</tr>'
                + '<tr><td class="px-5 py-3.5 text-[#333333]">MAPE</td>'
                + metricCell(sarima ? sarima.MAPE : null, mapeWinner === 'SARIMA', '%')
                + metricCell(xgboost ? xgboost.MAPE : null, mapeWinner === 'XGBoost', '%')
                + '</tr></tbody></table></div>'
                + '<p class="text-xs text-[#6B7280] mt-2">Lower values are better. Highlighted cells indicate the best performing model for each metric.</p>';
        }
        el.innerHTML = html;
    }

    function applyForecastData(data) {
        const formScope = document.getElementById('formScope');
        const formCage  = document.getElementById('formCage');
        const formBreed = document.getElementById('formBreed');
        if (formScope) formScope.value = data.scope || 'cage';
        if (formCage)  formCage.value  = data.cageCode || '';
        if (formBreed) formBreed.value = data.breed || '';

        setScopeButtonStates(data.scope);
        setScopeBlocks(data.scope);

        const cageEl = document.getElementById('cageSelect');
        if (cageEl) cageEl.value = data.cageCode || '';
        const breedEl = document.getElementById('breedSelect');
        if (breedEl) breedEl.value = data.breed || '';

        document.querySelectorAll('input[name="horizon"]').forEach(function(r) {
            r.checked = String(r.value) === String(data.horizon);
        });

        document.querySelectorAll('.forecast-target-label').forEach(function(el) {
            el.textContent = data.scopeLabel;
        });

        updateForecastSummary(data);
        updateModelComparison(data);
        if (window.lucide) {
            try { window.lucide.createIcons(); } catch (e) { /* non-fatal */ }
        }
    }

    let forecastRequestToken = 0;

    // Fetches fresh data for the current scope/cage/breed/horizon and updates the
    // workspace in place — no Turbo frame navigation, no URL change, no history churn.
    window.refreshForecastWorkspace = function() {
        const formScope = document.getElementById('formScope');
        if (!formScope) return;

        const scope = formScope.value || 'cage';
        const cageEl = document.getElementById('cageSelect');
        const breedEl = document.getElementById('breedSelect');
        const horizonEl = document.querySelector('input[name="horizon"]:checked');
        const formCage = document.getElementById('formCage');
        const formBreed = document.getElementById('formBreed');

        const params = new URLSearchParams({
            scope: scope,
            cage: cageEl ? cageEl.value : (formCage ? formCage.value : ''),
            breed: breedEl ? breedEl.value : (formBreed ? formBreed.value : ''),
            horizon: horizonEl ? horizonEl.value : '7',
        });

        const token = ++forecastRequestToken;

        fetch('/forecast/data?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (token !== forecastRequestToken) return;
                applyForecastData(data);
            })
            .catch(function(err) {
                if (token !== forecastRequestToken) return;
                console.error('[Forecast] refresh failed:', err);
            });
    };

    // Scope buttons: intercept the click and update the workspace in place instead
    // of navigating the turbo-frame (keeps the URL stable, no history churn). These
    // listeners are bound to the freshly-rendered links each time this script runs,
    // so no accumulation across Turbo visits.
    document.querySelectorAll('[data-forecast-scope]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const formScope = document.getElementById('formScope');
            if (formScope) formScope.value = this.getAttribute('data-forecast-scope');
            if (window.refreshForecastWorkspace) window.refreshForecastWorkspace();
        });
    });
})();
</script>
</turbo-frame>
