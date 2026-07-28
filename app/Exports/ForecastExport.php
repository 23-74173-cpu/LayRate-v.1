<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ForecastExport implements FromCollection, WithCustomStartCell, WithDrawings, WithHeadings, WithTitle
{
    private ?string $tempFile = null;

    public function __construct(
        private Collection $forecasts,
        private string $scope,
        private ?string $cageCode,
        private ?string $breed,
        ?string $imagePath = null
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
        $ts = 'Generated on: ' . now()->format('F j, Y  H:i');

        $rows = $this->forecasts->map(fn ($f) => [
            $f->target_date,
            $f->predicted_egg_count ?? 0,
            $f->confidence,
        ]);

        return collect([
            [$ts, '', ''],
            ['', '', ''],
        ])->concat($rows);
    }

    public function headings(): array
    {
        return ['Target Date', 'Predicted Egg Count', 'Confidence (%)'];
    }

    public function title(): string
    {
        $label = $this->scope === 'farm' ? 'Farm' : ($this->cageCode ?? $this->breed ?? 'Forecast');
        return 'Forecast - ' . $label;
    }

    public function startCell(): string
    {
        return $this->tempFile ? 'A9' : 'A3';
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
