<?php

namespace App\Http\Controllers;

use App\Enums\EggSize;
use App\Models\Cage;
use App\Models\EggStockBatch;
use App\Models\Hen;
use App\Services\QrSvg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Printable PDF sheets: egg stock QR labels and hen foot (leg band) tags.
 * Both lay their items out on the chosen paper with dashed cut lines, and
 * carry enough printed detail that a cut piece can be matched back to the
 * right tray or hen without scanning.
 */
class PrintableTagsController extends Controller
{
    /**
     * Paper sizes in millimetres. "Short" and "long" bond are the sizes
     * sold in the Philippines (8.5 x 11 in and 8.5 x 13 in).
     */
    public const PAPERS = [
        'a4'    => ['label' => 'A4 (210 × 297 mm)', 'w' => 210.0, 'h' => 297.0],
        'short' => ['label' => 'Short bond (8.5 × 11 in)', 'w' => 215.9, 'h' => 279.4],
        'long'  => ['label' => 'Long bond (8.5 × 13 in)', 'w' => 215.9, 'h' => 330.2],
    ];

    private const MARGIN_MM = 8;
    private const FOOTER_MM = 7;

    /**
     * Egg stock QR labels, 3 per row. Each label: the same QR payload as the
     * single-batch QR page (so the scanner reads both), plus size, count,
     * harvest date, cage, batch number and the fresh-until / old-after dates.
     */
    public function eggStockLabels(Request $request)
    {
        $data = $request->validate([
            'paper' => 'nullable|in:' . implode(',', array_keys(self::PAPERS)),
            'range' => 'nullable|in:all,7,30',
            'ids'   => 'nullable|array',
            'ids.*' => 'integer',
        ]);
        $paperKey = $data['paper'] ?? 'a4';
        $range = $data['range'] ?? 'all';

        $batches = EggStockBatch::with('cage')
            ->when(! empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']))
            ->when(empty($data['ids']) && $range !== 'all', fn ($q) => $q->where(
                'harvested_date', '>=', now()->subDays((int) $range - 1)->toDateString()
            ))
            ->orderByDesc('harvested_date')
            ->orderByDesc('id')
            ->get();

        $thresholds = EggStockBatch::freshnessThresholds();
        $labels = $batches->map(function (EggStockBatch $batch) use ($thresholds) {
            $harvested = $batch->harvested_date->copy();

            return (object) [
                'id'          => $batch->id,
                'qr'          => QrSvg::dataUri(self::eggStockPayload($batch)),
                'size'        => EggSize::labelFor($batch->egg_size),
                'count'       => (int) $batch->count,
                'trays'       => (int) ceil($batch->count / 30),
                'harvested'   => $harvested->format('m/d/Y'),
                'cage'        => $batch->cage?->cage_code ?? '—',
                'fresh_until' => $harvested->copy()->addDays($thresholds['fresh_days'])->format('m/d/Y'),
                'old_after'   => $harvested->copy()->addDays($thresholds['aging_days'])->format('m/d/Y'),
            ];
        });

        $layout = $this->grid($paperKey, 3, 40.0);

        return $this->pdf('printables.egg-stock-labels', [
            'pages'  => $labels->chunk($layout['per_page'])->map->values()->values(),
            'layout' => $layout,
            'total'  => $labels->count(),
            'range'  => $range,
        ], $paperKey, 'layrate_egg_qr_labels_' . now()->format('Ymd') . '.pdf');
    }

    /**
     * Hen foot tags: long strips that wrap around the leg, 2 per row. Each
     * strip shows the hen's tag code large, plus chicken ID, current cage
     * and slot, and breed, with a blank overlap tab at the end for gluing.
     */
    public function henFootTags(Request $request)
    {
        $data = $request->validate([
            'paper'   => 'nullable|in:' . implode(',', array_keys(self::PAPERS)),
            'cage_id' => 'nullable|integer|exists:cages,id',
        ]);
        $paperKey = $data['paper'] ?? 'a4';
        $cage = ! empty($data['cage_id']) ? Cage::find($data['cage_id']) : null;

        $hens = Hen::query()
            ->with('cageSlot.cage')
            ->where('is_active', 1)
            ->whereNotNull('cage_slot_id')
            ->whereHas('cageSlot.cage', fn ($q) => $q->when($cage, fn ($c) => $c->where('id', $cage->id)))
            ->get()
            ->sortBy(fn (Hen $hen) => sprintf(
                '%s|%05d|%05d|%s',
                $hen->cageSlot->cage->cage_code,
                $hen->cageSlot->row_number,
                $hen->cageSlot->column_number,
                $hen->tag_code ?? $hen->chicken_id ?? ''
            ))
            ->values();

        $tags = $hens->map(fn (Hen $hen) => (object) [
            'code'  => $hen->tag_code ?: ($hen->chicken_id ?: 'HEN-' . $hen->id),
            'id'    => $hen->chicken_id,
            'cage'  => $hen->cageSlot->cage->cage_code,
            'slot'  => $hen->cageSlot->row_number . '-' . $hen->cageSlot->column_number,
            'breed' => $hen->breed,
        ]);

        $layout = $this->grid($paperKey, 2, 13.0);

        return $this->pdf('printables.hen-foot-tags', [
            'pages'  => $tags->chunk($layout['per_page'])->map->values()->values(),
            'layout' => $layout,
            'total'  => $tags->count(),
            'cage'   => $cage,
        ], $paperKey, 'layrate_hen_foot_tags_' . ($cage?->cage_code ?? 'all') . '_' . now()->format('Ymd') . '.pdf');
    }

    /** The exact payload of EggStockController::qr(); the scanner parses it. */
    public static function eggStockPayload(EggStockBatch $batch): string
    {
        $cageCode = $batch->cage?->cage_code ?? 'UNKNOWN';

        return "LAYRATE|{$batch->id}|{$batch->harvested_date->toDateString()}|{$cageCode}|{$batch->egg_size}|{$batch->count}";
    }

    /** Cell size and items per page for a grid on the chosen paper. */
    private function grid(string $paperKey, int $columns, float $rowHeightMm): array
    {
        $paper = self::PAPERS[$paperKey];
        $usableW = $paper['w'] - 2 * self::MARGIN_MM;
        $usableH = $paper['h'] - 2 * self::MARGIN_MM - self::FOOTER_MM;
        // 3 mm spare so rounding in dompdf's table heights can never push the
        // last row onto an extra page (which also doubled the page count).
        $rows = max(1, (int) floor(($usableH - 3) / $rowHeightMm));

        return [
            'paper'    => $paper,
            'margin'   => self::MARGIN_MM,
            'columns'  => $columns,
            'rows'     => $rows,
            'per_page' => $rows * $columns,
            'cell_w'   => round($usableW / $columns, 2),
            'cell_h'   => $rowHeightMm,
        ];
    }

    private function pdf(string $view, array $data, string $paperKey, string $filename)
    {
        ini_set('memory_limit', '256M');
        $paper = self::PAPERS[$paperKey];
        $pt = fn (float $mm) => $mm * 72 / 25.4;

        // Font subsetting embeds only the glyphs used: ~900 KB -> ~50 KB per
        // sheet, which matters when opening it on a phone over the Pi's Wi-Fi.
        return Pdf::loadView($view, $data + ['printedAt' => now()])
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper([0, 0, $pt($paper['w']), $pt($paper['h'])], 'portrait')
            ->stream($filename);
    }
}
