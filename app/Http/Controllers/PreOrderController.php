<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\EggStockBatch;
use App\Models\Hen;
use App\Models\PreOrder;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreOrderController extends Controller
{
    public function table(Request $request)
    {
        $query = PreOrder::orderByDesc('requested_date');

        $statusFilter = $request->query('status');
        $sizeFilter = $request->query('egg_size');
        $fromFilter = $request->query('from');
        $toFilter = $request->query('to');

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if ($sizeFilter && $sizeFilter !== 'all') {
            $query->where('egg_size', $sizeFilter);
        }
        if ($fromFilter) {
            $query->where('requested_date', '>=', $fromFilter);
        }
        if ($toFilter) {
            $query->where('requested_date', '<=', $toFilter);
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('eggs.pre-orders._table', [
            'orders' => $orders,
        ]);
    }

    public function index(Request $request)
    {
        $query = PreOrder::orderByDesc('requested_date');

        $statusFilter = $request->query('status');
        $sizeFilter = $request->query('egg_size');
        $fromFilter = $request->query('from');
        $toFilter = $request->query('to');

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if ($sizeFilter && $sizeFilter !== 'all') {
            $query->where('egg_size', $sizeFilter);
        }
        if ($fromFilter) {
            $query->where('requested_date', '>=', $fromFilter);
        }
        if ($toFilter) {
            $query->where('requested_date', '<=', $toFilter);
        }

        $orders = $query->paginate(20)->withQueryString();

        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $summary = [];

        foreach ($sizes as $size) {
            $currentStock = EggStockBatch::where('egg_size', $size)->sum('count');
            $committed = PreOrder::where('egg_size', $size)->where('status', 'pending')->sum('egg_count');
            $forecasted = $this->forecastSize($size);
            $available = $currentStock + $forecasted - $committed;

            $summary[$size] = [
                'current_stock' => $currentStock,
                'committed' => $committed,
                'forecasted' => $forecasted,
                'available' => $available,
            ];
        }

        $this->runDepletionCheck($summary);

        $editOrder = null;
        if ($editOrderId = session('reopen_edit_order')) {
            $editOrder = PreOrder::find($editOrderId);
        }

        return view('eggs.pre-orders', [
            'activeTab' => 'preorders',
            'orders' => $orders,
            'summary' => $summary,
            'editOrder' => $editOrder,
            'filters' => [
                'status' => $statusFilter ?? 'all',
                'egg_size' => $sizeFilter ?? 'all',
                'from' => $fromFilter ?? '',
                'to' => $toFilter ?? '',
            ],
        ]);
    }

    public function poolData(Request $request)
    {
        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $pools = [];
        foreach ($sizes as $size) {
            $stocked = EggStockBatch::where('egg_size', $size)->sum('count');
            $committed = PreOrder::where('egg_size', $size)->where('status', 'pending')->sum('egg_count');
            $pools[$size] = $stocked - $committed;
        }
        return response()->json(['pools' => $pools]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_reference' => 'nullable|string|max:100',
            'egg_size' => 'required|in:small,medium,large,jumbo',
            'egg_count' => 'required|integer|min:1',
            'requested_date' => 'required|date',
            'fulfillment_date' => 'nullable|date|after_or_equal:requested_date',
            'notes' => 'nullable|string',
        ]);

        $validator->after(function ($v) {
            $count = $v->validated()['egg_count'] ?? 0;
            if ($count % 6 !== 0) {
                $v->errors()->add('egg_count', 'Order must be in multiples of 6 (half-dozen).');
            }
        });

        if ($validator->fails()) {
            return redirect()->route('eggs.preorders')
                ->with('reopen_add_order', true)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        try {
            PreOrder::createWithinPool($data);
        } catch (\OverflowException $e) {
            return redirect()->route('eggs.preorders')
                ->with('reopen_add_order', true)
                ->withErrors(['egg_count' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('eggs.preorders')->with('success', 'Pre-order added.');
    }

    public function update(Request $request, PreOrder $order)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_reference' => 'nullable|string|max:100',
            'egg_size' => 'required|in:small,medium,large,jumbo',
            'egg_count' => 'required|integer|min:1',
            'requested_date' => 'required|date',
            'fulfillment_date' => 'nullable|date|after_or_equal:requested_date',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,fulfilled,cancelled',
        ]);

        $validator->after(function ($v) {
            $count = $v->validated()['egg_count'] ?? 0;
            if ($count % 6 !== 0) {
                $v->errors()->add('egg_count', 'Order must be in multiples of 6 (half-dozen).');
            }
        });

        if ($validator->fails()) {
            return redirect()->route('eggs.preorders')
                ->with('reopen_edit_order', $order->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        if ($data['status'] === 'fulfilled' && empty($data['fulfillment_date'])) {
            $data['fulfillment_date'] = now()->toDateString();
        }

        try {
            $order->updateWithinPool($data);
        } catch (\OverflowException $e) {
            return redirect()->route('eggs.preorders')
                ->with('reopen_edit_order', $order->id)
                ->withErrors(['egg_count' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('eggs.preorders')->with('success', 'Pre-order updated.');
    }

    public function destroy(PreOrder $order)
    {
        $order->delete();

        return redirect()->route('eggs.preorders')->with('success', 'Pre-order cancelled.');
    }

    /**
     * Forecast eggs for a given size over the next 7 days.
     * Uses ForecastController's algorithm: average last 14 days HDEP × total active hens,
     * then distribute proportionally across sizes based on egg_size_logs distribution.
     * If egg_size_logs is empty, assumes equal 25% split per size.
     */
    private function forecastSize(string $size): int
    {
        $historical = ProductionLog::selectRaw('log_date, SUM(egg_count) as egg_count, SUM(hen_count) as hen_count')
            ->groupBy('log_date')
            ->orderByDesc('log_date')
            ->limit(14)
            ->get();

        if ($historical->isEmpty()) {
            return 0;
        }

        $avgHdep = $historical->avg(function ($row) {
            return $row->hen_count > 0 ? ($row->egg_count / $row->hen_count) * 100 : 0;
        }) ?? 85.0;

        $totalActiveHens = Hen::where('is_active', 1)->count();
        $avgDailyEggs = round(($avgHdep / 100) * $totalActiveHens);
        $sevenDayTotal = $avgDailyEggs * 7;

        $sizeDistribution = $this->getSizeDistribution();

        return (int) round($sevenDayTotal * $sizeDistribution[$size]);
    }

    /**
     * Determine the proportion of each egg size from historical egg_size_logs.
     * Falls back to equal 25% per size if no logs exist.
     */
    private function getSizeDistribution(): array
    {
        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $total = \App\Models\EggSizeLog::sum('count');

        if ($total === 0) {
            return array_fill_keys($sizes, 0.25);
        }

        $distribution = [];
        foreach ($sizes as $size) {
            $sizeCount = \App\Models\EggSizeLog::where('egg_size', $size)->sum('count');
            $distribution[$size] = $sizeCount / $total;
        }

        return $distribution;
    }

    /**
     * Check for stock depletion across all sizes and create alerts if needed.
     */
    private function runDepletionCheck(array $summary): void
    {
        foreach ($summary as $size => $data) {
            if ($data['available'] < 0) {
                $shortfall = abs($data['available']);
                $trays = (int) ceil($shortfall / 30);
                $message = "Pre-order demand for {$size} eggs exceeds supply by {$shortfall} eggs ({$trays} trays)";

                $exists = Alert::where('alert_type', 'stock_depletion')
                    ->where('message', 'like', "%{$size}%")
                    ->where('is_read', 0)
                    ->whereDate('triggered_at', now()->toDateString())
                    ->exists();

                if (!$exists) {
                    Alert::create([
                        'cage_id' => null,
                        'alert_type' => 'stock_depletion',
                        'message' => $message,
                        'is_read' => 0,
                        'triggered_at' => now(),
                    ]);
                }
            }
        }
    }
}
