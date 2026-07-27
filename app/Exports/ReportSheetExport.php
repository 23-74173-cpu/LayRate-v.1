<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ReportSheetExport implements FromCollection, WithCustomStartCell, WithDrawings, WithHeadings, WithTitle
{
    public function __construct(
        private string $label,
        private Collection $rows,
        private array $tempFiles = []
    ) {
    }

    /** Cleanup safety net — primary cleanup is register_shutdown_function in controller. */
    public function __destruct()
    {
        foreach ($this->tempFiles as $path) {
            file_exists($path) && @unlink($path);
        }
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn($row) => collect((array) $row)->values());
    }

    public function headings(): array
    {
        if ($this->rows->isEmpty()) {
            return [];
        }

        return array_map(
            fn($key) => strtoupper(str_replace('_', ' ', $key)),
            array_keys((array) $this->rows->first())
        );
    }

    public function title(): string
    {
        return Str::limit(preg_replace('/[:\\\\\/?*\[\]]/', '', $this->label), 31, '');
    }

    public function startCell(): string
    {
        $imageRows = count($this->tempFiles) * 14 + 2;
        return 'A' . max(1, $imageRows);
    }

    public function drawings(): array
    {
        $drawings = [];
        $rowOffset = 1;
        foreach ($this->tempFiles as $type => $path) {
            if (!file_exists($path)) continue;
            try {
                $drawing = new Drawing();
                $drawing->setName('Chart - ' . $type);
                $drawing->setPath($path);
                $drawing->setHeight(180);
                $drawing->setCoordinates('A' . $rowOffset);
                $drawings[] = $drawing;
                $rowOffset += 14;
            } catch (\Exception $e) {
                Log::warning("Excel export: failed to embed chart image [{$type}]: " . $e->getMessage());
            }
        }
        return $drawings;
    }
}
