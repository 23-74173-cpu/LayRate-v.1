<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EggStockBatch;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class EggStockController extends Controller
{
    public function index()
    {
        $batches = EggStockBatch::with(['cage', 'cageSlot', 'sourceProductionLog.cageSlot.cage'])
            ->orderByDesc('harvested_date')
            ->orderByDesc('created_at')
            ->get();

        $sizes = ['small', 'medium', 'large', 'jumbo'];

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

        return view('eggs.stocks', [
            'activeTab' => 'stocks',
            'batches' => $batches,
            'totals' => $totals,
            'trayTotals' => $trayTotals,
            'productionLogs' => $productionLogs,
            'cages' => $cages,
            'sizes' => $sizes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'egg_size' => 'required|in:small,medium,large,jumbo',
            'count' => 'required|integer|min:1',
            'harvested_date' => 'required|date',
            'cage_id' => 'required|exists:cages,id',
            'cage_slot_id' => 'nullable|exists:cage_slots,id',
            'source_production_log_id' => 'nullable|exists:production_logs,id',
        ]);

        $batch = EggStockBatch::create($data);

        $sizes = ['small', 'medium', 'large', 'jumbo'];
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

    public function destroy(EggStockBatch $batch)
    {
        $batch->delete();

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
