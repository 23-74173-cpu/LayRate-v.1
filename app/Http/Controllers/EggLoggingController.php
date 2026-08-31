<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Services\ReportingDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EggLoggingController extends Controller
{
    public function index(Request $request)
    {
        $today = ReportingDateService::reportingDateString();
        $cageFilter = $request->query('cage_id');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $cageSlots = CageSlot::with([
            'cage',
            'hens' => fn ($q) => $q->where('is_active', 1),
        ])->whereHas('cage', fn ($q) => $q->where('is_active', 1))
            ->orderBy('cage_id')
            ->orderBy('slot_number')
            ->get();

        $slotIds = $cageSlots->pluck('id');
        $todayLogs = ProductionLog::whereIn('cage_slot_id', $slotIds)
            ->where('log_date', $today)
            ->get()
            ->keyBy('cage_slot_id');

        $cageSlots->each(function ($slot) use ($todayLogs) {
            $slot->today_egg_count = $todayLogs->get($slot->id)?->egg_count ?? 0;
        });

        $henCountByCage = $cages->mapWithKeys(fn ($cage) => [
            $cage->id => $cageSlots->where('cage_id', $cage->id)->sum(fn ($slot) => $slot->hens->count())
        ]);

        $todayTotal = ProductionLog::where('log_date', $today)
            ->when($cageFilter, fn ($q) => $q->whereHas('cageSlot', fn ($s) => $s->where('cage_id', $cageFilter)))
            ->sum('egg_count');

        $todayData = ProductionLog::with('cageSlot.cage')
            ->where('log_date', $today)
            ->get()
            ->groupBy(fn ($l) => $l->cageSlot?->cage?->cage_code);

        $todayByCage = $todayData->map(fn ($g) => $g->sum('egg_count'));
        $todayLoggedCountByCage = $todayData->map(fn ($g) => $g->count());

        $selectedCage = $cageFilter ? $cages->firstWhere('id', $cageFilter) : null;

        $editLog = null;
        if ($editLogId = session('reopen_edit_log')) {
            $editLog = ProductionLog::with('eggSizeLogs')->find($editLogId);
        }

        return view('egg-logging', compact(
            'cageSlots', 'cages', 'cageFilter', 'todayTotal', 'todayByCage', 'todayLoggedCountByCage', 'selectedCage', 'editLog', 'henCountByCage'
        ));
    }

    public function recentLogs(Request $request)
    {
        $filters = $this->logFilters($request);

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();
        $cageSlots = CageSlot::with('cage')
            ->when($filters['cage_id'], fn ($q, $cageId) => $q->where('cage_id', $cageId))
            ->orderBy('cage_id')
            ->orderBy('slot_number')
            ->get();

        $breeds = Hen::where('is_active', 1)
            ->selectRaw('distinct breed')
            ->orderBy('breed')
            ->pluck('breed');

        $logsQuery = $this->buildFilteredLogsQuery($request);
        $logs = $logsQuery->paginate(20)->withQueryString();

        return view('eggs.recent-logs', compact(
            'logs', 'cages', 'cageSlots', 'breeds', 'filters'
        ));
    }

    public function logs(Request $request)
    {
        $filters = $this->logFilters($request);
        $logsQuery = $this->buildFilteredLogsQuery($request);
        $logs = $logsQuery->paginate(5)->withQueryString();

        return view('egg-logging._logs', compact('logs', 'filters'));
    }

    private function logFilters(Request $request): array
    {
        return [
            'cage_id' => $request->query('cage_id'),
            'cage_slot_id' => $request->query('cage_slot_id'),
            'breed' => $request->query('breed'),
            'logged_via' => $request->query('logged_via', []),
        ];
    }

    private function buildFilteredLogsQuery(Request $request)
    {
        $filters = $this->logFilters($request);

        $query = ProductionLog::with(['cageSlot.cage', 'overriddenBy', 'recorder', 'eggSizeLogs'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at');

        if ($filters['cage_id']) {
            $query->whereHas('cageSlot', fn ($q) => $q->where('cage_id', $filters['cage_id']));
        }

        if ($filters['cage_slot_id']) {
            $query->where('cage_slot_id', $filters['cage_slot_id']);
        }

        if ($filters['breed']) {
            $query->whereHas('cageSlot.hens', fn ($q) => $q->where('breed', $filters['breed'])->where('is_active', 1));
        }

        if (! empty($filters['logged_via'])) {
            $query->whereIn('logged_via', (array) $filters['logged_via']);
        }

        return $query;
    }

    public function verifyOverride(Request $request)
    {
        $data = $request->validate([
            'cage_slot_id' => 'required|exists:cage_slots,id',
            'pin' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $user = auth()->user();

        if ($user->override_pin_hash !== null) {
            if (! $data['pin'] || ! Hash::check($data['pin'], $user->override_pin_hash)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect PIN.'], 422);
            }
        } else {
            if (! $data['password'] || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect password.'], 422);
            }
        }

        session()->put("override_verified_slot.{$data['cage_slot_id']}", now()->timestamp);

        return response()->json([
            'ok' => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $isAjax = $request->wantsJson() || $request->ajax();

        $validator = Validator::make($request->all(), [
            'log_date' => 'required|date|before_or_equal:'.ReportingDateService::reportingDateString(),
            'cage_slot_ids' => 'nullable|array',
            'cage_slot_ids.*' => 'exists:cage_slots,id',
            'cage_slot_id' => 'nullable|exists:cage_slots,id',
            'egg_count' => 'required|integer|min:0',
            'notes' => 'nullable|string',
            'size_small' => 'nullable|integer|min:0',
            'size_medium' => 'nullable|integer|min:0',
            'size_large' => 'nullable|integer|min:0',
            'size_jumbo' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            if ($isAjax) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $sizeSum = (int) ($data['size_small'] ?? 0)
                 + (int) ($data['size_medium'] ?? 0)
                 + (int) ($data['size_large'] ?? 0)
                 + (int) ($data['size_jumbo'] ?? 0);

        $anySizeFilled = ($data['size_small'] ?? 0) > 0
                      || ($data['size_medium'] ?? 0) > 0
                      || ($data['size_large'] ?? 0) > 0
                      || ($data['size_jumbo'] ?? 0) > 0;

        if ($anySizeFilled && $sizeSum !== (int) $data['egg_count']) {
            if ($isAjax) {
                return response()->json(['success' => false, 'errors' => ['size_breakdown' => "Size breakdown sum ({$sizeSum}) must equal total eggs ({$data['egg_count']})."]], 422);
            }
            return redirect()->back()
                ->withErrors(['size_breakdown' => "Size breakdown sum ({$sizeSum}) must equal total eggs ({$data['egg_count']})."])
                ->withInput();
        }

        $slotIds = $data['cage_slot_ids'] ?? [$data['cage_slot_id']];
        $isMulti = count($slotIds) > 1;

        $slots = CageSlot::with('cage')->whereIn('id', $slotIds)->get()->keyBy('id');

        $cageIds = $slots->pluck('cage_id')->unique();
        $diedTodayByCage = [];
        foreach ($cageIds as $cid) {
            $died = (int) DB::table('mortality_logs')
                ->join('mortality_log_hens', 'mortality_logs.id', '=', 'mortality_log_hens.mortality_log_id')
                ->where('mortality_logs.cage_id', $cid)
                ->where('mortality_logs.log_date', $data['log_date'])
                ->distinct()
                ->count('mortality_log_hens.hen_id');

            if ($died === 0) {
                $died = (int) MortalityLog::where('cage_id', $cid)
                    ->where('log_date', $data['log_date'])
                    ->sum('count');
            }
            $diedTodayByCage[$cid] = $died;
        }

        // Validate all slots before saving any
        foreach ($slotIds as $sid) {
            $slot = $slots->get($sid);
            if (!$slot) continue;

            $henCount = $slot->active_hen_count + ($diedTodayByCage[$slot->cage_id] ?? 0);

            if ($henCount === 0) {
                if ($isAjax) {
                    return response()->json(['success' => false, 'errors' => ['cage_slot_id' => "Slot {$slot->slot_number} has no hens assigned."]], 422);
                }
                return redirect()->back()
                    ->withErrors(['cage_slot_id' => "Slot {$slot->slot_number} has no hens assigned. Cannot log egg production for an empty slot."])
                    ->withInput();
            }

            if ($data['egg_count'] > $henCount) {
                if ($isAjax) {
                    return response()->json(['success' => false, 'errors' => ['egg_count' => "Egg count ({$data['egg_count']}) cannot exceed hen count ({$henCount}) in slot {$slot->slot_number}."]], 422);
                }
                return redirect()->back()
                    ->withErrors(['egg_count' => "Egg count ({$data['egg_count']}) cannot exceed hen count ({$henCount}) in slot {$slot->slot_number}."])
                    ->withInput();
            }
        }

        $savedLogs = [];
        foreach ($slotIds as $sid) {
            $slot = $slots->get($sid);
            if (!$slot) continue;

            $henCount = $slot->active_hen_count + ($diedTodayByCage[$slot->cage_id] ?? 0);

            $log = ProductionLog::firstOrNew(
                ['cage_slot_id' => $slot->id, 'log_date' => $data['log_date']]
            );
            $log->cage_slot_id = $slot->id;
            $log->recorded_by = auth()->id();
            $log->fill([
                'egg_count' => $data['egg_count'],
                'hen_count' => $henCount,
                'hdep' => round(($data['egg_count'] / $henCount) * 100, 2),
                'notes' => $data['notes'] ?? 'Manual entry',
                'logged_via' => $data['logged_via'] ?? 'manual',
            ]);
            $log->save();

            $this->syncSizeLogs($log, $data);

            $savedLogs[] = [
                'id' => $log->id,
                'cage_slot_id' => $log->cage_slot_id,
                'egg_count' => $log->egg_count,
                'hen_count' => $log->hen_count,
                'hdep' => $log->hdep,
                'log_date' => $log->log_date->toDateString(),
            ];
        }

        if ($isAjax) {
            $response = ['success' => true, 'message' => 'Production log saved.', 'reminder' => true];
            if ($isMulti) {
                $response['logs'] = $savedLogs;
            } else {
                $response['log'] = $savedLogs[0];
            }
            return response()->json($response);
        }

        return redirect()->route('eggs.logging')
            ->with('success', 'Production log saved.')
            ->with('showFeedEnvReminder', true);
    }

    public function update(Request $request, ProductionLog $productionLog)
    {
        $validator = Validator::make($request->all(), [
            'log_date' => 'required|date|before_or_equal:'.ReportingDateService::reportingDateString(),
            'egg_count' => 'required|integer|min:0',
            'hen_count' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'size_small' => 'nullable|integer|min:0',
            'size_medium' => 'nullable|integer|min:0',
            'size_large' => 'nullable|integer|min:0',
            'size_jumbo' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->route('eggs.logging')
                ->with('reopen_edit_log', $productionLog->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $sizeSum = (int) ($data['size_small'] ?? 0)
                 + (int) ($data['size_medium'] ?? 0)
                 + (int) ($data['size_large'] ?? 0)
                 + (int) ($data['size_jumbo'] ?? 0);

        $anySizeFilled = ($data['size_small'] ?? 0) > 0
                      || ($data['size_medium'] ?? 0) > 0
                      || ($data['size_large'] ?? 0) > 0
                      || ($data['size_jumbo'] ?? 0) > 0;

        if ($anySizeFilled && $sizeSum !== (int) $data['egg_count']) {
            return redirect()->route('eggs.logging')
                ->with('reopen_edit_log', $productionLog->id)
                ->withErrors(['size_breakdown' => "Size breakdown sum ({$sizeSum}) must equal total eggs ({$data['egg_count']})."])
                ->withInput();
        }

        // Use hen_count submitted by the editor, or preserve the stored value
        $henCount = $data['hen_count'] !== null ? (int) $data['hen_count'] : $productionLog->hen_count;

        if ($henCount === 0) {
            return redirect()->route('eggs.logging')
                ->with('reopen_edit_log', $productionLog->id)
                ->withErrors(['hen_count' => 'Hen count cannot be zero.'])
                ->withInput();
        }

        if ($data['egg_count'] > $henCount) {
            return redirect()->route('eggs.logging')
                ->with('reopen_edit_log', $productionLog->id)
                ->withErrors(['egg_count' => "Egg count ({$data['egg_count']}) cannot exceed hen count ({$henCount})."])
                ->withInput();
        }

        $productionLog->update([
            'log_date' => $data['log_date'],
            'egg_count' => $data['egg_count'],
            'hen_count' => $henCount,
            'hdep' => round(($data['egg_count'] / $henCount) * 100, 2),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncSizeLogs($productionLog, $data);

        return redirect()->route('eggs.logging')->with('success', 'Production log updated.');
    }

    private function syncSizeLogs(ProductionLog $log, array $data): void
    {
        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $hasNonZero = false;

        foreach ($sizes as $size) {
            if (((int) ($data["size_{$size}"] ?? 0)) > 0) {
                $hasNonZero = true;
                break;
            }
        }

        EggSizeLog::where('production_log_id', $log->id)->delete();

        if ($hasNonZero) {
            foreach ($sizes as $size) {
                $count = (int) ($data["size_{$size}"] ?? 0);
                if ($count > 0) {
                    $log->eggSizeLogs()->create([
                        'egg_size' => $size,
                        'count' => $count,
                    ]);
                }
            }
        } elseif ($log->egg_count > 0) {
            $log->eggSizeLogs()->create([
                'egg_size' => 'unsorted',
                'count' => $log->egg_count,
            ]);
        }
    }

    public function resetCount(ProductionLog $productionLog)
    {
        $productionLog->update([
            'egg_count' => 0,
            'hdep' => 0,
            'notes' => $productionLog->notes
                ? $productionLog->notes . ' | Reset to 0'
                : 'Reset to 0',
        ]);

        $productionLog->eggSizeLogs()->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('eggs.logging')->with('success', 'Egg count reset to 0.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();

        return redirect()->route('eggs.logging')->with('success', 'Log deleted.');
    }
}
