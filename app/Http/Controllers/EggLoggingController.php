<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\Hen;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EggLoggingController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $cageFilter = $request->query('cage_id');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $cageSlots = CageSlot::with([
            'cage',
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->whereHas('cage', fn($q) => $q->where('is_active', 1))
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

        $todayTotal = ProductionLog::where('log_date', $today)
            ->when($cageFilter, fn($q) => $q->whereHas('cageSlot', fn($s) => $s->where('cage_id', $cageFilter)))
            ->sum('egg_count');

        $todayData = ProductionLog::with('cageSlot.cage')
            ->where('log_date', $today)
            ->get()
            ->groupBy(fn($l) => $l->cageSlot?->cage?->cage_code);

        $todayByCage = $todayData->map(fn($g) => $g->sum('egg_count'));
        $todayLoggedCountByCage = $todayData->map(fn($g) => $g->count());

        $selectedCage = $cageFilter ? $cages->firstWhere('id', $cageFilter) : null;

        return view('egg-logging', compact(
            'cageSlots', 'cages', 'cageFilter', 'todayTotal', 'todayByCage', 'todayLoggedCountByCage', 'selectedCage'
        ));
    }

    public function recentLogs(Request $request)
    {
        $filters = $this->logFilters($request);

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();
        $cageSlots = CageSlot::with('cage')
            ->when($filters['cage_id'], fn($q, $cageId) => $q->where('cage_id', $cageId))
            ->orderBy('cage_id')
            ->orderBy('slot_number')
            ->get();

        $breeds = \App\Models\Hen::where('is_active', 1)
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
        $logs = $logsQuery->paginate(20)->withQueryString();

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
            $query->whereHas('cageSlot', fn($q) => $q->where('cage_id', $filters['cage_id']));
        }

        if ($filters['cage_slot_id']) {
            $query->where('cage_slot_id', $filters['cage_slot_id']);
        }

        if ($filters['breed']) {
            $query->whereHas('cageSlot.hens', fn($q) => $q->where('breed', $filters['breed'])->where('is_active', 1));
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
            'pin'          => 'nullable|string',
            'password'     => 'nullable|string',
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
            'ok'              => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'log_date'     => 'required|date',
            'cage_slot_id' => 'required|exists:cage_slots,id',
            'egg_count'    => 'required|integer|min:0',
            'notes'        => 'nullable|string',
            'size_small'   => 'nullable|integer|min:0',
            'size_medium'  => 'nullable|integer|min:0',
            'size_large'   => 'nullable|integer|min:0',
            'size_jumbo'   => 'nullable|integer|min:0',
        ]);

        $sizeSum = (int) ($data['size_small'] ?? 0)
                 + (int) ($data['size_medium'] ?? 0)
                 + (int) ($data['size_large'] ?? 0)
                 + (int) ($data['size_jumbo'] ?? 0);

        $anySizeFilled = ($data['size_small'] ?? 0) > 0
                      || ($data['size_medium'] ?? 0) > 0
                      || ($data['size_large'] ?? 0) > 0
                      || ($data['size_jumbo'] ?? 0) > 0;

        if ($anySizeFilled && $sizeSum !== (int) $data['egg_count']) {
            return redirect()->back()
                ->withErrors(['size_breakdown' => "Size breakdown sum ({$sizeSum}) must equal total eggs ({$data['egg_count']})."])
                ->withInput();
        }

        $slot = CageSlot::with('cage')->findOrFail($data['cage_slot_id']);

        $henCount = $slot->active_hen_count;
        if ($henCount === 0) {
            return redirect()->back()
                ->withErrors(['cage_slot_id' => 'This slot has no hens assigned. Cannot log egg production for an empty slot.'])
                ->withInput();
        }

        $log = ProductionLog::firstOrNew(
            ['cage_slot_id' => $slot->id, 'log_date' => $data['log_date']]
        );
        $log->cage_slot_id = $slot->id;
        $log->recorded_by = auth()->id();
        $log->fill([
            'egg_count' => $data['egg_count'],
            'hen_count' => $henCount,
            'hdep'       => round(($data['egg_count'] / $henCount) * 100, 2),
            'notes'      => $data['notes'] ?? 'Manual entry',
            // Web form submissions are always manual. Future sensor ingestion must
            // create ProductionLog records with logged_via = 'sensor' explicitly.
            'logged_via' => $data['logged_via'] ?? 'manual',
        ]);
        $log->save();

        $this->syncSizeLogs($log, $data);

        return redirect()->route('eggs.logging')->with('success', 'Production log saved.');
    }

    public function update(Request $request, ProductionLog $productionLog)
    {
        $data = $request->validate([
            'log_date'    => 'required|date',
            'egg_count'   => 'required|integer|min:0',
            'notes'       => 'nullable|string',
            'size_small'  => 'nullable|integer|min:0',
            'size_medium' => 'nullable|integer|min:0',
            'size_large'  => 'nullable|integer|min:0',
            'size_jumbo'  => 'nullable|integer|min:0',
        ]);

        $sizeSum = (int) ($data['size_small'] ?? 0)
                 + (int) ($data['size_medium'] ?? 0)
                 + (int) ($data['size_large'] ?? 0)
                 + (int) ($data['size_jumbo'] ?? 0);

        $anySizeFilled = ($data['size_small'] ?? 0) > 0
                      || ($data['size_medium'] ?? 0) > 0
                      || ($data['size_large'] ?? 0) > 0
                      || ($data['size_jumbo'] ?? 0) > 0;

        if ($anySizeFilled && $sizeSum !== (int) $data['egg_count']) {
            return redirect()->back()
                ->withErrors(['size_breakdown' => "Size breakdown sum ({$sizeSum}) must equal total eggs ({$data['egg_count']})."])
                ->withInput();
        }

        $slot = $productionLog->cageSlot;
        if (!$slot || $slot->active_hen_count === 0) {
            return redirect()->back()
                ->withErrors(['cage_slot_id' => 'This slot has no hens assigned. Cannot update production log for an empty slot.'])
                ->withInput();
        }
        $henCount = $slot->active_hen_count;

        $productionLog->update([
            'log_date'  => $data['log_date'],
            'egg_count' => $data['egg_count'],
            'hen_count' => $henCount,
            'hdep'      => round(($data['egg_count'] / $henCount) * 100, 2),
            'notes'     => $data['notes'] ?? null,
        ]);

        $this->syncSizeLogs($productionLog, $data);

        return redirect()->route('eggs.logging')->with('success', 'Production log updated.');
    }

    private function syncSizeLogs(ProductionLog $log, array $data): void
    {
        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $anyFilled = false;

        foreach ($sizes as $size) {
            if (($data["size_{$size}"] ?? null) !== null) {
                $anyFilled = true;
                break;
            }
        }

        EggSizeLog::where('production_log_id', $log->id)->delete();

        if ($anyFilled) {
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

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('eggs.logging')->with('success', 'Log deleted.');
    }
}
