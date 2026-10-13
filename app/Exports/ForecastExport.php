<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use App\Services\ReportingDateService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ForecastExport implements FromCollection, WithCustomStartCell, WithDrawings, WithHeadings, WithTitle, WithStyles
{
    private ?string $tempFile = null;

    public function __construct(
        private Collection $forecasts,
        private string $scope,
        private ?string $cageCode,
        private ?string $breed,
        ?string $imagePath = null,
        private string $forecastView = 'saved'
    ) {
        $this->tempFile = $imagePath;
    }

    public function __destruct()
    {
        if ($this->tempFile && file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    public function collection(): Collection
    {
        $ts = 'Exported on: ' . ReportingDateService::now()->format('F j, Y  H:i');
        $label = $this->scope === 'farm' ? 'Whole Farm' : ($this->scope === 'breed' ? $this->breed : $this->cageCode);
        $type = $this->forecastView === 'backtest' ? 'Historical Test' : 'Forecast';

        $rows = $this->forecasts->map(fn ($f) => [
            $f->target_date->toDateString(),
            $f->predicted_egg_count ?? 0,
            $f->forecast_date->toDateString(),
        ]);

        return collect([
            [$ts, '', ''],
            ['Scope: '.$label.' | Type: '.$type, '', ''],
            ['Forecast uncertainty generally increases with time. Predictions for dates farther ahead may differ more from actual production.', '', ''],
        ])->concat($rows);
    }

    public function headings(): array
    {
        return ['Target Date', 'Predicted Egg Count', 'Issued Date'];
    }

    public function title(): string
    {
        $label = $this->scope === 'farm' ? 'Farm' : ($this->scope === 'breed' ? ($this->breed ?? 'Breed') : ($this->cageCode ?? 'Cage'));
        $title = ($this->forecastView === 'backtest' ? 'Historical Test - ' : 'Forecast - ') . $label;
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $title), 0, 31);
    }

    public function startCell(): string
    {
        return $this->tempFile ? 'A9' : 'A3';
    }

    public function styles(Worksheet $sheet): array
    {
        $heading = $this->tempFile ? 9 : 3;
        foreach (['A' => 20, 'B' => 25, 'C' => 20] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        foreach (range($heading + 1, $heading + 3) as $row) {
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
        }
        $sheet->getRowDimension($heading + 3)->setRowHeight(42);
        $sheet->freezePane('A'.($heading + 4));
        return [
            $heading => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '002D5E']]],
        ];
    }

    public function drawings()
    {
        if (!$this->tempFile) {
            return [];
        }

        try {
            $drawing = new Drawing();
            $drawing->setName('Forecast Chart');
            $drawing->setDescription('Forecast chart');
            $drawing->setPath($this->tempFile);
            $drawing->setHeight(250);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(10);
            return [$drawing];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to embed forecast chart in Excel: ' . $e->getMessage());
            return [];
        }
    }
}
