<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EggSizeLog;
use App\Models\EggStockBatch;
use App\Models\ProductionLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EggStockController extends Controller
{
    public function liveData(Request $request)
    {
        // Totals/tray counts must reflect every batch, not just the current
        // page — computed from a separate unpaginated query.
        $allBatches = EggStockBatch::query();
        $sizes = ['small', 'medium', 'large', 'jumbo', 'unsorted'];

        $totals = [];
        $trayTotals = [];
        foreach ($sizes as $size) {
            $totals[$size] = (clone $allBatches)->where('egg_size', $size)->sum('count');
            $trayTotals[$size] = (int) ceil($totals[$size] / 30);
        }

        $batches = EggStockBatch::with(['cage', 'cageSlot', 'sourceProductionLog.cageSlot.cage'])
            ->orderByDesc('harvested_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $availablePools = EggStockBatch::getAvailablePools();

        return view('eggs.stocks._live-data', [
            'batches' => $batches,
            'totals' => $totals,
            'trayTotals' => $trayTotals,
            'sizes' => $sizes,
            'availablePools' => $availablePools,
            'eggStockThresholds' => EggStockBatch::sizeThresholds(),
            'freshnessThresholds' => EggStockBatch::freshnessThresholds(),
        ]);
    }

    public function saveEggWeights(Request $request)
    {
        $data = $request->validate([
            'egg_weight_small'    => 'required|numeric|min:1|max:500',
            'egg_weight_medium'   => 'required|numeric|min:1|max:500',
            'egg_weight_large'    => 'required|numeric|min:1|max:500',
            'egg_weight_jumbo'    => 'required|numeric|min:1|max:500',
            'egg_weight_fallback' => 'required|numeric|min:1|max:500',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->route('eggs.stocks')
            ->with('success', 'Egg weights saved.');
    }

    public function poolData(Request $request)
    {
        $cageId = $request->integer('cage_id');
        if ($cageId < 1) $cageId = null;

        $pools = EggStockBatch::getAvailablePools($cageId);

        return response()->json(['pools' => $pools]);
    }

    public function index()
    {
        $batches = EggStockBatch::with(['cage', 'cageSlot', 'sourceProductionLog.cageSlot.cage'])
            ->orderByDesc('harvested_date')
            ->orderByDesc('created_at')
            ->get();

        $sizes = ['small', 'medium', 'large', 'jumbo', 'unsorted'];

        $totals = [];
        $trayTotals = [];
        foreach ($sizes as $size) {
            $totals[$size] = $batches->where('egg_size', $size)->sum('count');
            $trayTotals[$size] = (int) ceil($totals[$size] / 30);
        }

        $productionLogs = ProductionLog::with(['cageSlot.cage'])
            ->where('log_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn($log) => $log->cageSlot?->cage?->id ?? 'unknown');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $availablePools = EggStockBatch::getAvailablePools();

        $eggWeights = Setting::eggWeights();

        $editBatch = null;
        if ($editBatchId = session('reopen_edit_stock')) {
            $editBatch = EggStockBatch::find($editBatchId);
        }

        return view('eggs.stocks', [
            'activeTab' => 'stocks',
            'editBatch' => $editBatch,
            'batches' => $batches,
            'totals' => $totals,
            'trayTotals' => $trayTotals,
            'availablePools' => $availablePools,
            'productionLogs' => $productionLogs,
            'cages' => $cages,
            'sizes' => $sizes,
            'eggWeights' => $eggWeights,
            'eggStockThresholds' => EggStockBatch::sizeThresholds(),
            'freshnessThresholds' => EggStockBatch::freshnessThresholds(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'egg_size' => 'required|in:small,medium,large,jumbo,unsorted',
            'count' => 'required|integer|min:1',
            'harvested_date' => 'required|date',
            'cage_id' => 'nullable|exists:cages,id,is_active,1',
            'cage_slot_id' => 'nullable|exists:cage_slots,id',
            'source_production_log_id' => 'nullable|exists:production_logs,id',
            'classify' => 'nullable|boolean',
            'classify_small' => 'nullable|integer|min:0',
            'classify_medium' => 'nullable|integer|min:0',
            'classify_large' => 'nullable|integer|min:0',
            'classify_jumbo' => 'nullable|integer|min:0',
        ]);

        if ($data['egg_size'] === 'unsorted' && ($request->boolean('classify'))) {
            return $this->storeClassified($data);
        }

        if ($data['egg_size'] !== 'unsorted' && !$request->boolean('classify')) {
            $available = EggStockBatch::getAvailablePoolForSize($data['egg_size'], cageId: $data['cage_id'] ?? null);
            if (($data['count'] ?? 0) > $available) {
                return $this->stockWithAutoReclassify($data);
            }
        }

        try {
            $batch = EggStockBatch::createWithinPool($data);
        } catch (\OverflowException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['count' => [$e->getMessage()]],
            ], 422);
        }

        EggStockBatch::checkLowStock();

        $sizes = ['small', 'medium', 'large', 'jumbo', 'unsorted'];
        $allBatches = EggStockBatch::all();
        $newTotals = [];
        foreach ($sizes as $size) {
            $newTotals[$size] = $allBatches->where('egg_size', $size)->sum('count');
        }

        return response()->json([
            'success' => true,
            'batch' => [
                'id' => $batch->id,
                'egg_size' => $batch->egg_size,
                'count' => $batch->count,
                'harvested_date' => $batch->harvested_date->toDateString(),
                'freshness_status' => $batch->freshness_status,
                'cage_code' => $batch->cage?->cage_code ?? '—',
                'cage_color' => $batch->cage?->color ?? '#6B7280',
                'cage_color_soft' => $batch->cage?->color_soft ?? '#f0f0f0',
            ],
            'totals' => $newTotals,
            'trayTotals' => array_map(fn($t) => (int) ceil($t / 30), $newTotals),
        ])->header('Content-Type', 'application/json');
    }

    private function stockWithAutoReclassify(array $data): \Illuminate\Http\JsonResponse
    {
        try {
            DB::transaction(function () use ($data) {
                $size = $data['egg_size'];
                $count = (int) $data['count'];
                $cageId = $data['cage_id'] ?? null;

                $unsortedQuery = EggSizeLog::where('egg_size', 'unsorted')
                    ->where('count', '>', 0);
                if ($cageId) {
                    $unsortedQuery->whereHas('productionLog.cageSlot', fn($q) => $q->where('cage_id', $cageId));
                }
                $unsortedRecords = $unsortedQuery->lockForUpdate()->orderBy('id')->get();

                $remaining = $count;
                $sourceLogId = null;
                foreach ($unsortedRecords as $record) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, $record->count);
                    $record->decrement('count', $deduct);
                    $remaining -= $deduct;
                    if ($sourceLogId === null) {
                        $sourceLogId = $record->production_log_id;
                    }
                }

                $reclassified = $count - $remaining;

                if ($reclassified > 0) {
                    EggSizeLog::create([
                        'production_log_id' => $sourceLogId,
                        'egg_size' => $size,
                        'count' => $reclassified,
                    ]);
                }

                EggStockBatch::create([
                    'egg_size' => $size,
                    'count' => $count,
                    'harvested_date' => $data['harvested_date'],
                    'cage_id' => $cageId,
                    'cage_slot_id' => $data['cage_slot_id'] ?? null,
                    'source_production_log_id' => $data['source_production_log_id'] ?? $sourceLogId,
                ]);
            });
        } catch (\OverflowException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['count' => [$e->getMessage()]],
            ], 422);
        }

        EggStockBatch::checkLowStock();

        return response()->json(['success' => true])
            ->header('Content-Type', 'application/json');
    }

    private function storeClassified(array $data): \Illuminate\Http\JsonResponse
    {
        $classifySizes = [];
        foreach (['small', 'medium', 'large', 'jumbo'] as $size) {
            $val = (int) ($data["classify_{$size}"] ?? 0);
            if ($val > 0) $classifySizes[$size] = $val;
        }

        $classifiedTotal = array_sum($classifySizes);

        if ($classifiedTotal === 0) {
            return response()->json([
                'success' => false,
                'errors' => ['count' => ['Specify at least one size to classify into, or stock as Unsorted without classification.']],
            ], 422);
        }

        if ($classifiedTotal !== (int) $data['count']) {
            return response()->json([
                'success' => false,
                'errors' => ['classify_small' => ['Classified amounts must sum to the total egg count.']],
            ], 422);
        }

        try {
            DB::transaction(function () use ($data, $classifySizes, $classifiedTotal) {
                $cageId = $data['cage_id'] ?? null;

                $unsortedQuery = EggSizeLog::where('egg_size', 'unsorted')
                    ->where('count', '>', 0);
                if ($cageId) {
                    $unsortedQuery->whereHas('productionLog.cageSlot', fn($q) => $q->where('cage_id', $cageId));
                }
                $unsortedRecords = $unsortedQuery->lockForUpdate()->orderBy('id')->get();

                $remaining = $classifiedTotal;
                $sourceLogId = null;
                foreach ($unsortedRecords as $record) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, $record->count);
                    $record->decrement('count', $deduct);
                    $remaining -= $deduct;
                    if ($sourceLogId === null) {
                        $sourceLogId = $record->production_log_id;
                    }
                }

                if ($remaining > 0) {
                    throw new \OverflowException('Not enough unsorted eggs available to classify.');
                }

                foreach ($classifySizes as $size => $count) {
                    EggSizeLog::create([
                        'production_log_id' => $sourceLogId,
                        'egg_size' => $size,
                        'count' => $count,
                    ]);

                    EggStockBatch::create([
                        'egg_size' => $size,
                        'count' => $count,
                        'harvested_date' => $data['harvested_date'],
                        'cage_id' => $data['cage_id'] ?? null,
                        'cage_slot_id' => $data['cage_slot_id'] ?? null,
                        'source_production_log_id' => $data['source_production_log_id'] ?? $sourceLogId,
                    ]);
                }
            });
        } catch (\OverflowException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['count' => [$e->getMessage()]],
            ], 422);
        }

        EggStockBatch::checkLowStock();

        return response()->json(['success' => true])
            ->header('Content-Type', 'application/json');
    }

    public function update(Request $request, EggStockBatch $batch)
    {
        $validator = Validator::make($request->all(), [
            'egg_size' => 'required|in:small,medium,large,jumbo,unsorted',
            'count' => 'required|integer|min:1',
            'harvested_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return redirect()->route('eggs.stocks')
                ->with('reopen_edit_stock', $batch->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        try {
            $batch->updateWithinPool($data);
        } catch (\OverflowException $e) {
            return redirect()->route('eggs.stocks')
                ->with('reopen_edit_stock', $batch->id)
                ->withErrors(['count' => $e->getMessage()])
                ->withInput();
        }

        $batch->refresh();

        EggStockBatch::checkLowStock();

        return redirect()->route('eggs.stocks')->with('success', 'Stock batch updated.');
    }

    public function saveThresholds(Request $request)
    {
        $data = $request->validate([
            'egg_low_stock_threshold_small'    => 'nullable|integer|min:0',
            'egg_low_stock_threshold_medium'   => 'nullable|integer|min:0',
            'egg_low_stock_threshold_large'    => 'nullable|integer|min:0',
            'egg_low_stock_threshold_jumbo'    => 'nullable|integer|min:0',
            'egg_low_stock_threshold_unsorted' => 'nullable|integer|min:0',
            'egg_freshness_fresh_days'         => 'nullable|integer|min:1|max:365',
            'egg_freshness_aging_days'         => 'nullable|integer|min:1|max:365',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, (int) $value);
        }

        EggStockBatch::checkLowStock();

        return back()->with('success', 'Egg stock configuration saved.');
    }

    public function destroy(EggStockBatch $batch)
    {
        $batch->delete();

        EggStockBatch::checkLowStock();

        return back()->with('success', 'Stock batch deleted.');
    }

    public function qr(EggStockBatch $batch)
    {
        $cageCode = $batch->cage?->cage_code ?? 'UNKNOWN';
        $qrData = "LAYRATE|{$batch->id}|{$batch->harvested_date->toDateString()}|{$cageCode}|{$batch->egg_size}|{$batch->count}";

        return view('eggs.qr-print', [
            'batch' => $batch,
            'qrData' => $qrData,
        ]);
    }
}
